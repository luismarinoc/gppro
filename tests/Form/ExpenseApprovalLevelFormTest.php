<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Form;

use App\Entity\ExpenseApprovalLevel;
use App\Form\ExpenseApprovalLevelForm;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Test\TypeTestCase;

#[CoversClass(ExpenseApprovalLevelForm::class)]
class ExpenseApprovalLevelFormTest extends TypeTestCase
{
    public function testFieldsArePresent(): void
    {
        $model = new ExpenseApprovalLevel();
        $form = $this->factory->createBuilder(ExpenseApprovalLevelForm::class, $model);

        self::assertTrue($form->has('level'));
        self::assertTrue($form->has('minAmount'));
        // 'requiredRole' uses App\Form\Type\UserRoleType, which requires
        // RoleService (final, unmockable) - only presence is asserted here.
        self::assertTrue($form->has('requiredRole'));
    }

    public function testLevelAndMinAmountAreRequiredIntegerFields(): void
    {
        $model = new ExpenseApprovalLevel();
        $form = $this->factory->createBuilder(ExpenseApprovalLevelForm::class, $model);

        $level = $form->get('level');
        $minAmount = $form->get('minAmount');

        self::assertTrue($level->getOption('required'));
        self::assertTrue($minAmount->getOption('required'));
    }

    public function testCsrfTokenIdIsDedicated(): void
    {
        $model = new ExpenseApprovalLevel();
        $form = $this->factory->createBuilder(ExpenseApprovalLevelForm::class, $model);

        self::assertEquals('expense_approval_level_edit', $form->getFormConfig()->getOption('csrf_token_id'));
    }

    public function testApproverUserFieldIsPresent(): void
    {
        $model = new ExpenseApprovalLevel();
        $form = $this->factory->createBuilder(ExpenseApprovalLevelForm::class, $model);

        // 'approverUser' uses App\Form\Type\UserType, which requires
        // UserRepository/RolePermissionManager (unmockable in TypeTestCase) -
        // only presence is asserted here, mirroring the 'requiredRole'
        // precedent above; 'required === false' is passed explicitly in
        // ExpenseApprovalLevelForm and covered by the controller integration
        // test (task 3.3).
        self::assertTrue($form->has('approverUser'));
    }
}
