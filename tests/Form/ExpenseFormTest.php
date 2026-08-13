<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Form;

use App\Configuration\LocaleService;
use App\Entity\Expense;
use App\Form\ExpenseForm;
use App\Form\Type\DatePickerType;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

#[CoversClass(ExpenseForm::class)]
class ExpenseFormTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        // LocaleService is final, so it cannot be mocked: build a real instance
        // covering whatever locale this environment resolves as default.
        $localeService = new LocaleService([
            \Locale::getDefault() => LocaleService::DEFAULT_SETTINGS,
        ]);

        return [
            new PreloadedExtension([new DatePickerType($localeService)], []),
        ];
    }

    public function testFieldsArePresent(): void
    {
        $model = new Expense();
        $form = $this->factory->createBuilder(ExpenseForm::class, $model);

        self::assertTrue($form->has('description'));
        self::assertTrue($form->has('amount'));
        self::assertTrue($form->has('expenseDate'));
        self::assertTrue($form->has('recurrence'));
        // 'allocations' resolves a CollectionType prototype through
        // ExpenseAllocationForm's ProjectType field, which needs services not
        // registered in this narrow TypeTestCase (ProjectHelper/CustomerHelper
        // are final and cannot be mocked) - only presence is asserted here,
        // matching the project's own ProjectEditFormTest precedent for
        // unresolvable custom-typed fields.
        self::assertTrue($form->has('allocations'));
    }

    public function testAmountFieldIsRequired(): void
    {
        $model = new Expense();
        $form = $this->factory->createBuilder(ExpenseForm::class, $model);

        $amount = $form->get('amount');

        // Expense::$amount is a native PHP int (whole CLP, design D3) -
        // IntegerType is the core type dedicated to native int properties.
        self::assertTrue($amount->getOption('required'));
    }

    public function testExpenseDateFieldUsesDatetimeImmutableToMatchEntityType(): void
    {
        $model = new Expense();
        $form = $this->factory->createBuilder(ExpenseForm::class, $model);

        $date = $form->get('expenseDate');

        // Expense::$expenseDate is typed \DateTimeImmutable - the base DateType
        // would otherwise hand back a mutable \DateTime and break data_class binding.
        self::assertEquals('datetime_immutable', $date->getOption('input'));
    }

    public function testRecurrenceFieldIsOptionalAndRestrictedToMonth(): void
    {
        $model = new Expense();
        $form = $this->factory->createBuilder(ExpenseForm::class, $model);

        $recurrence = $form->get('recurrence');

        self::assertFalse($recurrence->getOption('required'));

        $choices = $recurrence->getOption('choices');
        self::assertIsArray($choices);
        self::assertContains(Expense::RECURRENCE_MONTH, $choices);
        self::assertCount(\count(Expense::RECURRENCES), $choices);
    }

    public function testCsrfTokenIdIsDedicated(): void
    {
        $model = new Expense();
        $form = $this->factory->createBuilder(ExpenseForm::class, $model);

        self::assertEquals('expense_edit', $form->getFormConfig()->getOption('csrf_token_id'));
    }
}
