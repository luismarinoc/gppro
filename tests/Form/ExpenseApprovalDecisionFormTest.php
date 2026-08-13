<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Form;

use App\Form\ExpenseApprovalDecisionForm;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[CoversClass(ExpenseApprovalDecisionForm::class)]
class ExpenseApprovalDecisionFormTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        // A real standalone validator (not the base TypeTestCase mock, which
        // always returns zero violations) is required to actually exercise
        // the 'note' field's Length constraint below.
        return [new ValidatorExtension(Validation::createValidator())];
    }

    public function testNoteFieldIsPresentAndOptional(): void
    {
        $form = $this->factory->createBuilder(ExpenseApprovalDecisionForm::class);

        self::assertTrue($form->has('note'));
        self::assertFalse($form->get('note')->getOption('required'));
    }

    public function testNoteAcceptsAndRejectsOverLongText(): void
    {
        $form = $this->factory->createBuilder(ExpenseApprovalDecisionForm::class)->getForm();
        $form->submit(['note' => str_repeat('a', 1001)]);

        self::assertFalse($form->isValid());
    }

    public function testNoteSubmitsSuccessfullyWhenWithinLimit(): void
    {
        $form = $this->factory->createBuilder(ExpenseApprovalDecisionForm::class)->getForm();
        $form->submit(['note' => 'Looks good']);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        $data = $form->getData();
        self::assertIsArray($data);
        self::assertSame('Looks good', $data['note']);
    }

    public function testCsrfTokenIdIsDedicated(): void
    {
        $form = $this->factory->createBuilder(ExpenseApprovalDecisionForm::class);

        self::assertEquals('expense_approval_decision', $form->getFormConfig()->getOption('csrf_token_id'));
    }
}
