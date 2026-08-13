<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Voter;

use App\Entity\Expense;
use App\Entity\ExpenseApprovalLevel;
use App\Entity\User;
use App\Expense\ExpenseApprovalPolicy;
use App\Repository\ExpenseApprovalLevelRepository;
use App\Repository\ExpenseApprovalRepository;
use App\Voter\ExpenseVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[CoversClass(ExpenseVoter::class)]
class ExpenseVoterTest extends AbstractVoterTestCase
{
    public function testViewExpenseRequiresDedicatedViewPermission(): void
    {
        $expense = new Expense();
        $user = self::getUser(1, User::ROLE_ADMIN);

        $this->assertVote($user, $expense, 'view_expense', VoterInterface::ACCESS_DENIED, []);
    }

    public function testUserWithPermissionCanViewADraftExpense(): void
    {
        $expense = new Expense();
        $user = self::getUser(1, User::ROLE_TEAMLEAD);

        $this->assertVote($user, $expense, 'view_expense', VoterInterface::ACCESS_GRANTED, ['view_expense']);
    }

    public function testEditIsGrantedForDraftExpenseWithPermission(): void
    {
        $expense = new Expense();
        $user = self::getUser(1, User::ROLE_TEAMLEAD);

        $this->assertVote($user, $expense, 'edit_expense', VoterInterface::ACCESS_GRANTED, ['edit_expense']);
    }

    public function testEditIsDeniedForApprovedExpenseEvenWithPermission(): void
    {
        $expense = new Expense();
        $expense->submitForApproval(1);
        $expense->clearLevel(1);
        $user = self::getUser(1, User::ROLE_TEAMLEAD);

        $this->assertVote($user, $expense, 'edit_expense', VoterInterface::ACCESS_DENIED, ['edit_expense']);
    }

    public function testDeleteIsGrantedForRejectedExpenseWithPermission(): void
    {
        $expense = new Expense();
        $expense->submitForApproval(1);
        $expense->rejectApproval();
        $user = self::getUser(1, User::ROLE_SUPER_ADMIN);

        $this->assertVote($user, $expense, 'delete_expense', VoterInterface::ACCESS_GRANTED, ['delete_expense']);
    }

    public function testDeleteIsGrantedForDraftExpenseWithPermission(): void
    {
        $expense = new Expense();
        $user = self::getUser(1, User::ROLE_SUPER_ADMIN);

        $this->assertVote($user, $expense, 'delete_expense', VoterInterface::ACCESS_GRANTED, ['delete_expense']);
    }

    public function testDeleteIsDeniedForApprovedExpenseEvenWithPermission(): void
    {
        $expense = new Expense();
        $expense->submitForApproval(1);
        $expense->clearLevel(1);
        $user = self::getUser(1, User::ROLE_SUPER_ADMIN);

        $this->assertVote($user, $expense, 'delete_expense', VoterInterface::ACCESS_DENIED, ['delete_expense']);
    }

    public function testChargeIsGrantedForApprovedExpenseWithPermission(): void
    {
        $expense = new Expense();
        $expense->submitForApproval(1);
        $expense->clearLevel(1);
        $user = self::getUser(1, User::ROLE_TEAMLEAD);

        $this->assertVote($user, $expense, 'charge_expense', VoterInterface::ACCESS_GRANTED, ['charge_expense']);
    }

    public function testChargeIsDeniedForDraftExpenseEvenWithPermission(): void
    {
        $expense = new Expense();
        $user = self::getUser(1, User::ROLE_TEAMLEAD);

        $this->assertVote($user, $expense, 'charge_expense', VoterInterface::ACCESS_DENIED, ['charge_expense']);
    }

    public function testApproveExpenseGrantedForCorrectRoleApprover(): void
    {
        $creator = self::getUser(1, null);
        $approver = self::getUser(2, User::ROLE_TEAMLEAD);
        $expense = $this->makePendingExpense($creator);

        $this->assertApprovalVote($approver, $expense, 'approve_expense', VoterInterface::ACCESS_GRANTED, [$this->makeLevel(1, User::ROLE_TEAMLEAD)]);
    }

    public function testApproveExpenseDeniedForCreator(): void
    {
        $creator = self::getUser(1, User::ROLE_TEAMLEAD);
        $expense = $this->makePendingExpense($creator);

        $this->assertApprovalVote($creator, $expense, 'approve_expense', VoterInterface::ACCESS_DENIED, [$this->makeLevel(1, User::ROLE_TEAMLEAD)]);
    }

    public function testRejectExpenseGrantedForCorrectRoleApprover(): void
    {
        $creator = self::getUser(1, null);
        $rejecter = self::getUser(2, User::ROLE_TEAMLEAD);
        $expense = $this->makePendingExpense($creator);

        $this->assertApprovalVote($rejecter, $expense, 'reject_expense', VoterInterface::ACCESS_GRANTED, [$this->makeLevel(1, User::ROLE_TEAMLEAD)]);
    }

    public function testRejectExpenseDeniedForCreator(): void
    {
        $creator = self::getUser(1, User::ROLE_TEAMLEAD);
        $expense = $this->makePendingExpense($creator);

        $this->assertApprovalVote($creator, $expense, 'reject_expense', VoterInterface::ACCESS_DENIED, [$this->makeLevel(1, User::ROLE_TEAMLEAD)]);
    }

    private function makeLevel(int $level, string $requiredRole): ExpenseApprovalLevel
    {
        return (new ExpenseApprovalLevel())
            ->setLevel($level)
            ->setMinAmount(0)
            ->setRequiredRole($requiredRole);
    }

    private function makePendingExpense(User $creator, int $requiredLevels = 1): Expense
    {
        $expense = (new Expense())->setCreatedBy($creator);
        $expense->submitForApproval($requiredLevels);

        return $expense;
    }

    /**
     * @param ExpenseApprovalLevel[] $levels
     */
    private function assertApprovalVote(User $user, Expense $expense, string $attribute, int $expected, array $levels): void
    {
        $levelRepository = $this->createMock(ExpenseApprovalLevelRepository::class);
        $levelRepository->method('findAllOrdered')->willReturn($levels);

        $approvalRepository = $this->createMock(ExpenseApprovalRepository::class);
        $approvalRepository->method('hasUserApprovedAnyLevel')->willReturn(false);

        $policy = new ExpenseApprovalPolicy($levelRepository, $approvalRepository);
        $voter = new ExpenseVoter($this->getRolePermissionManager(), $policy);
        $token = new UsernamePasswordToken($user, 'test', $user->getRoles());

        self::assertSame($expected, $voter->vote($token, $expense, [$attribute]));
    }

    /**
     * @param string[] $permissions
     */
    private function assertVote(User $user, Expense $expense, string $attribute, int $expected, array $permissions): void
    {
        $manager = $this->getRolePermissionManager([
            'ROLE_TEAMLEAD' => $permissions,
            'ROLE_ADMIN' => $permissions,
            'ROLE_SUPER_ADMIN' => $permissions,
        ], true);
        $levelRepository = $this->createMock(ExpenseApprovalLevelRepository::class);
        $approvalRepository = $this->createMock(ExpenseApprovalRepository::class);
        $policy = new ExpenseApprovalPolicy($levelRepository, $approvalRepository);
        $voter = new ExpenseVoter($manager, $policy);
        $token = new UsernamePasswordToken($user, 'test', $user->getRoles());

        self::assertSame($expected, $voter->vote($token, $expense, [$attribute]));
    }
}
