<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseApprovalLevel;
use App\Entity\User;
use App\Expense\ExpenseApprovalPolicy;
use App\Repository\ExpenseApprovalLevelRepository;
use App\Repository\ExpenseApprovalRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpenseApprovalPolicy::class)]
class ExpenseApprovalPolicyTest extends TestCase
{
    private function makeUser(string ...$roles): User
    {
        $user = new User();
        $user->setUsername('user-' . uniqid());
        foreach ($roles as $role) {
            $user->addRole($role);
        }

        return $user;
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
    private function makeSut(array $levels, bool $userAlreadyApproved = false): ExpenseApprovalPolicy
    {
        $levelRepository = $this->createMock(ExpenseApprovalLevelRepository::class);
        $levelRepository->method('findAllOrdered')->willReturn($levels);

        $approvalRepository = $this->createMock(ExpenseApprovalRepository::class);
        $approvalRepository->method('hasUserApprovedAnyLevel')->willReturn($userAlreadyApproved);

        return new ExpenseApprovalPolicy($levelRepository, $approvalRepository);
    }

    public function testUserWithMatchingRoleCanApproveThePendingLevel(): void
    {
        $creator = $this->makeUser();
        $approver = $this->makeUser('ROLE_TEAMLEAD');
        $expense = $this->makePendingExpense($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertTrue($sut->canApprove($expense, $approver));
    }

    public function testCreatorCannotApproveOwnExpense(): void
    {
        $creator = $this->makeUser('ROLE_TEAMLEAD');
        $expense = $this->makePendingExpense($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertFalse($sut->canApprove($expense, $creator));
    }

    public function testUserWhoAlreadyClearedALevelCannotClearAnother(): void
    {
        $creator = $this->makeUser();
        $approver = $this->makeUser('ROLE_ADMIN');
        $expense = $this->makePendingExpense($creator, 2);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD'), $this->makeLevel(2, 'ROLE_ADMIN')], userAlreadyApproved: true);

        self::assertFalse($sut->canApprove($expense, $approver));
    }

    public function testSuperAdminApprovesRegardlessOfConfiguredRole(): void
    {
        $creator = $this->makeUser();
        $approver = $this->makeUser('ROLE_SUPER_ADMIN');
        $expense = $this->makePendingExpense($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertTrue($sut->canApprove($expense, $approver));
    }

    public function testUserWithoutTheRequiredRoleCannotApprove(): void
    {
        $creator = $this->makeUser();
        $approver = $this->makeUser('ROLE_USER');
        $expense = $this->makePendingExpense($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertFalse($sut->canApprove($expense, $approver));
    }

    public function testUserWithMatchingRoleCanRejectThePendingLevel(): void
    {
        $creator = $this->makeUser();
        $rejecter = $this->makeUser('ROLE_TEAMLEAD');
        $expense = $this->makePendingExpense($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertTrue($sut->canReject($expense, $rejecter));
    }

    public function testCreatorCannotRejectOwnExpense(): void
    {
        $creator = $this->makeUser('ROLE_TEAMLEAD');
        $expense = $this->makePendingExpense($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertFalse($sut->canReject($expense, $creator));
    }
}
