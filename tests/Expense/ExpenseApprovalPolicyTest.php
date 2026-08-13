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

    private function makeLevel(int $level, string $requiredRole, ?User $approverUser = null): ExpenseApprovalLevel
    {
        return (new ExpenseApprovalLevel())
            ->setLevel($level)
            ->setMinAmount(0)
            ->setRequiredRole($requiredRole)
            ->setApproverUser($approverUser);
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

    /**
     * D2 branch-ordering invariant (RED-first, MANDATORY): the named-approver
     * match must never bypass the creator exclusion.
     */
    public function testCreatorNamedAsApproverIsStillDenied(): void
    {
        $creator = $this->makeUser();
        $expense = $this->makePendingExpense($creator);
        $level = $this->makeLevel(1, 'ROLE_TEAMLEAD', $creator);
        $sut = $this->makeSut([$level]);

        self::assertFalse($sut->canApprove($expense, $creator));
    }

    /**
     * D2 branch-ordering invariant (RED-first, MANDATORY): the named-approver
     * match must never bypass the distinct-approver (four-eyes) exclusion.
     */
    public function testAlreadyApprovedUserNamedOnLaterLevelIsStillDenied(): void
    {
        $creator = $this->makeUser();
        $approver = $this->makeUser('ROLE_ADMIN');
        $expense = $this->makePendingExpense($creator, 2);
        $levelTwo = $this->makeLevel(2, 'ROLE_ADMIN', $approver);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD'), $levelTwo], userAlreadyApproved: true);

        self::assertFalse($sut->canApprove($expense, $approver));
    }

    public function testNamedApproverClearsALevelWithoutHoldingTheRole(): void
    {
        $creator = $this->makeUser();
        $namedApprover = $this->makeUser(); // holds no roles
        $expense = $this->makePendingExpense($creator);
        $level = $this->makeLevel(1, 'ROLE_TEAMLEAD', $namedApprover);
        $sut = $this->makeSut([$level]);

        self::assertTrue($sut->canApprove($expense, $namedApprover));
    }

    public function testRoleHolderClearsALevelThatNamesADifferentApprover(): void
    {
        $creator = $this->makeUser();
        $namedApprover = $this->makeUser();
        $roleHolder = $this->makeUser('ROLE_TEAMLEAD');
        $expense = $this->makePendingExpense($creator);
        $level = $this->makeLevel(1, 'ROLE_TEAMLEAD', $namedApprover);
        $sut = $this->makeSut([$level]);

        self::assertTrue($sut->canApprove($expense, $roleHolder));
    }

    public function testReassigningTheNamedApproverAppliesLiveToAPendingLevel(): void
    {
        $creator = $this->makeUser();
        $originalApprover = $this->makeUser();
        $newApprover = $this->makeUser();
        $expense = $this->makePendingExpense($creator);
        $level = $this->makeLevel(1, 'ROLE_TEAMLEAD', $originalApprover);
        $sut = $this->makeSut([$level]);

        self::assertTrue($sut->canApprove($expense, $originalApprover));

        $level->setApproverUser($newApprover);

        self::assertTrue($sut->canApprove($expense, $newApprover));
        self::assertFalse($sut->canApprove($expense, $originalApprover));
    }
}
