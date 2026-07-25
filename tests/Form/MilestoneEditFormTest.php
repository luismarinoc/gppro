<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Form;

use App\Configuration\LocaleService;
use App\Entity\Milestone;
use App\Form\MilestoneEditForm;
use App\Form\Type\DatePickerType;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

#[CoversClass(MilestoneEditForm::class)]
class MilestoneEditFormTest extends TypeTestCase
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
        $model = new Milestone();
        $form = $this->factory->createBuilder(MilestoneEditForm::class, $model);

        self::assertTrue($form->has('name'));
        self::assertTrue($form->has('dueDate'));
        self::assertTrue($form->has('comment'));
        self::assertTrue($form->has('value'));
        self::assertTrue($form->has('currency'));
    }

    public function testValueFieldPreservesDecimalPrecisionAsStringAndIsOptional(): void
    {
        $model = new Milestone();
        $form = $this->factory->createBuilder(MilestoneEditForm::class, $model);

        $value = $form->get('value');

        // Milestone::$value is DECIMAL(18,4) mapped as string in Doctrine: the
        // NumberType MUST keep 'input' => 'string' and 'scale' => 4, otherwise PHP
        // float rounding silently corrupts the amount (same rule as FxRate::$rateValue).
        self::assertEquals('string', $value->getOption('input'));
        self::assertEquals(4, $value->getOption('scale'));
        self::assertFalse($value->getOption('required'));
    }

    public function testCurrencyFieldIsOptionalAndRestrictedToSupportedCurrencies(): void
    {
        $model = new Milestone();
        $form = $this->factory->createBuilder(MilestoneEditForm::class, $model);

        $currency = $form->get('currency');

        self::assertFalse($currency->getOption('required'));

        $choices = $currency->getOption('choices');
        self::assertIsArray($choices);
        self::assertCount(\count(Milestone::SUPPORTED_CURRENCIES), $choices);
        foreach (Milestone::SUPPORTED_CURRENCIES as $currencyCode) {
            self::assertContains($currencyCode, $choices);
        }
    }

    public function testValueAndCurrencyAreEmptyByDefaultForNewMilestone(): void
    {
        $model = new Milestone();
        $form = $this->factory->createBuilder(MilestoneEditForm::class, $model)->getForm();
        $form->submit(['name' => 'Kickoff', 'value' => '', 'currency' => '']);

        self::assertTrue($form->isSynchronized());
        self::assertNull($model->getValue());
        self::assertNull($model->getCurrency());
    }

    public function testValueAndCurrencySubmitPersistBothFields(): void
    {
        $model = new Milestone();
        $form = $this->factory->createBuilder(MilestoneEditForm::class, $model)->getForm();
        $form->submit(['name' => 'Delivery', 'value' => '5000.1234', 'currency' => 'USD']);

        self::assertTrue($form->isSynchronized());
        self::assertSame('5000.1234', $model->getValue());
        self::assertSame('USD', $model->getCurrency());
    }

    public function testCsrfTokenIdIsUnchanged(): void
    {
        $model = new Milestone();
        $form = $this->factory->createBuilder(MilestoneEditForm::class, $model);

        self::assertEquals('admin_milestone_edit', $form->getFormConfig()->getOption('csrf_token_id'));
    }
}
