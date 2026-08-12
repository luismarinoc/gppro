<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Expense;

use App\Entity\Customer;
use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Entity\ExpenseApproval;
use App\Entity\ExpenseApprovalLevel;
use App\Entity\Project;
use App\Entity\User;
use App\Expense\ApprovalLevelResolver;
use App\Expense\ExpenseApprovalPolicy;
use App\Expense\ExpenseApprovalService;
use App\Repository\ExpenseApprovalLevelRepository;
use App\Repository\ExpenseApprovalRepository;
use App\Repository\ExpenseRepository;
use App\Tests\Repository\AbstractRepositoryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ExpenseApprovalService::class)]
#[Group('integration')]
class ExpenseApprovalServiceTest extends AbstractRepositoryTestCase
{
    private function getSut(): ExpenseApprovalService
    {
        $em = $this->getEntityManager();

        /** @var ExpenseApprovalLevelRepository $levelRepository */
        $levelRepository = $em->getRepository(ExpenseApprovalLevel::class);
        /** @var ExpenseApprovalRepository $approvalRepository */
        $approvalRepository = $em->getRepository(ExpenseApproval::class);

        return new ExpenseApprovalService(
            $em,
            new ApprovalLevelResolver($levelRepository),
            new ExpenseApprovalPolicy($levelRepository, $approvalRepository),
        );
    }

    private function getExpenseRepository(): ExpenseRepository
    {
        /** @var ExpenseRepository $repository */
        $repository = $this->getEntityManager()->getRepository(Expense::class);

        return $repository;
    }

    private function addLevel(int $level, int $minAmount, string $requiredRole): void
    {
        /** @var ExpenseApprovalLevelRepository $repository */
        $repository = $this->getEntityManager()->getRepository(ExpenseApprovalLevel::class);
        $repository->saveLevel((new ExpenseApprovalLevel())->setLevel($level)->setMinAmount($minAmount)->setRequiredRole($requiredRole));
    }

    private function createProject(): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Expense approval service test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Expense approval service test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);
        $em->flush();

        return $project;
    }

    private function createUser(string ...$roles): User
    {
        $em = $this->getEntityManager();

        $suffix = uniqid();
        $user = new User();
        $user->setUsername('expense-approval-service-test-' . $suffix);
        $user->setEmail('expense-approval-service-test-' . $suffix . '@example.com');
        $user->setPassword('irrelevant');
        $user->setEnabled(true);
        foreach ($roles as $role) {
            $user->addRole($role);
        }
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createDraftExpense(Project $project, User $creator, int $amount): Expense
    {
        $expense = new Expense();
        $expense->setDescription('Expense ' . uniqid());
        $expense->setAmount($amount);
        $expense->setExpenseDate(new \DateTimeImmutable('today'));
        $expense->setCreatedBy($creator);
        $expense->addAllocation((new ExpenseAllocation())->setProject($project)->setPercentage('100.00')->setAmountClp($amount));

        $this->getExpenseRepository()->saveExpense($expense);

        return $expense;
    }

    public function testSubmitComputesAndFreezesRequiredLevels(): void
    {
        $this->addLevel(2, 500000, 'ROLE_ADMIN');
        $project = $this->createProject();
        $creator = $this->createUser();
        $expense = $this->createDraftExpense($project, $creator, 1000000);

        $submitted = $this->getSut()->submit($expense);

        self::assertSame(Expense::STATUS_PENDING_APPROVAL, $submitted->getStatus());
        self::assertSame(2, $submitted->getRequiredLevels());
        self::assertSame(0, $submitted->getCurrentLevel());
    }

    public function testApproveSingleLevelExpenseRecordsAuditRowAndMovesToApproved(): void
    {
        $project = $this->createProject();
        $creator = $this->createUser();
        $approver = $this->createUser('ROLE_TEAMLEAD');
        $expense = $this->createDraftExpense($project, $creator, 100000);
        $this->getSut()->submit($expense);

        $approved = $this->getSut()->approve($expense, $approver, 'looks good');

        self::assertSame(Expense::STATUS_APPROVED, $approved->getStatus());
        self::assertSame(1, $approved->getCurrentLevel());

        /** @var ExpenseApprovalRepository $approvalRepository */
        $approvalRepository = $this->getEntityManager()->getRepository(ExpenseApproval::class);
        $approvals = $approvalRepository->findByExpense($approved);
        self::assertCount(1, $approvals);
        self::assertSame(ExpenseApproval::DECISION_APPROVED, $approvals[0]->getDecision());
        self::assertSame('looks good', $approvals[0]->getNote());
    }

    public function testApproveByUnauthorizedApproverIsRejected(): void
    {
        $project = $this->createProject();
        $creator = $this->createUser();
        $expense = $this->createDraftExpense($project, $creator, 100000);
        $this->getSut()->submit($expense);

        $this->expectException(\DomainException::class);

        $this->getSut()->approve($expense, $creator);
    }

    public function testTwoLevelExpenseRequiresBothApproversBeforeApproved(): void
    {
        $this->addLevel(2, 500000, 'ROLE_ADMIN');
        $project = $this->createProject();
        $creator = $this->createUser();
        $teamlead = $this->createUser('ROLE_TEAMLEAD');
        $admin = $this->createUser('ROLE_ADMIN');
        $expense = $this->createDraftExpense($project, $creator, 1000000);
        $this->getSut()->submit($expense);

        $afterFirst = $this->getSut()->approve($expense, $teamlead);
        self::assertSame(Expense::STATUS_PENDING_APPROVAL, $afterFirst->getStatus());
        self::assertSame(1, $afterFirst->getCurrentLevel());

        $afterSecond = $this->getSut()->approve($expense, $admin);
        self::assertSame(Expense::STATUS_APPROVED, $afterSecond->getStatus());
        self::assertSame(2, $afterSecond->getCurrentLevel());
    }

    public function testRejectAtSecondLevelDiscardsFirstLevelAndMovesToRejected(): void
    {
        $this->addLevel(2, 500000, 'ROLE_ADMIN');
        $project = $this->createProject();
        $creator = $this->createUser();
        $teamlead = $this->createUser('ROLE_TEAMLEAD');
        $admin = $this->createUser('ROLE_ADMIN');
        $expense = $this->createDraftExpense($project, $creator, 1000000);
        $this->getSut()->submit($expense);
        $this->getSut()->approve($expense, $teamlead);

        $rejected = $this->getSut()->reject($expense, $admin, 'not valid');

        self::assertSame(Expense::STATUS_REJECTED, $rejected->getStatus());
        self::assertSame(0, $rejected->getCurrentLevel());

        /** @var ExpenseApprovalRepository $approvalRepository */
        $approvalRepository = $this->getEntityManager()->getRepository(ExpenseApproval::class);
        $approvals = $approvalRepository->findByExpense($rejected);
        self::assertCount(2, $approvals);
        self::assertSame(ExpenseApproval::DECISION_APPROVED, $approvals[0]->getDecision());
        self::assertSame(ExpenseApproval::DECISION_REJECTED, $approvals[1]->getDecision());
    }
}
