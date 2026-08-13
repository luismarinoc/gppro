<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\Customer;
use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Entity\ExpenseApproval;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ExpenseApprovalRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ExpenseApprovalRepository::class)]
#[Group('integration')]
class ExpenseApprovalRepositoryTest extends AbstractRepositoryTestCase
{
    private function getRepository(): ExpenseApprovalRepository
    {
        /** @var ExpenseApprovalRepository $repository */
        $repository = $this->getEntityManager()->getRepository(ExpenseApproval::class);

        return $repository;
    }

    private function createProject(): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Expense approval repo test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Expense approval repo test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);
        $em->flush();

        return $project;
    }

    private function createUser(): User
    {
        $em = $this->getEntityManager();

        $suffix = uniqid();
        $user = new User();
        $user->setUsername('expense-approval-repo-test-' . $suffix);
        $user->setEmail('expense-approval-repo-test-' . $suffix . '@example.com');
        $user->setPassword('irrelevant');
        $user->setEnabled(true);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createExpense(Project $project): Expense
    {
        $em = $this->getEntityManager();

        $expense = new Expense();
        $expense->setDescription('Expense ' . uniqid());
        $expense->setAmount(100000);
        $expense->setExpenseDate(new \DateTimeImmutable('today'));
        $expense->addAllocation((new ExpenseAllocation())->setProject($project)->setPercentage('100.00')->setAmountClp(100000));
        $em->persist($expense);
        $em->flush();

        return $expense;
    }

    public function testFindByExpenseReturnsApprovalsOrderedByLevel(): void
    {
        $em = $this->getEntityManager();
        $repository = $this->getRepository();
        $project = $this->createProject();
        $expense = $this->createExpense($project);
        $approver = $this->createUser();

        $second = (new ExpenseApproval())->setExpense($expense)->setLevel(2)->setDecision(ExpenseApproval::DECISION_APPROVED)->setApprovedBy($approver)->setApprovedAt(new \DateTimeImmutable());
        $first = (new ExpenseApproval())->setExpense($expense)->setLevel(1)->setDecision(ExpenseApproval::DECISION_APPROVED)->setApprovedBy($approver)->setApprovedAt(new \DateTimeImmutable());
        $em->persist($second);
        $em->persist($first);
        $em->flush();

        $results = $repository->findByExpense($expense);
        $levels = array_map(static fn (ExpenseApproval $a): ?int => $a->getLevel(), $results);

        self::assertSame([1, 2], $levels);
    }

    public function testHasUserApprovedAnyLevel(): void
    {
        $em = $this->getEntityManager();
        $repository = $this->getRepository();
        $project = $this->createProject();
        $expense = $this->createExpense($project);
        $approver = $this->createUser();
        $otherUser = $this->createUser();

        $approval = (new ExpenseApproval())->setExpense($expense)->setLevel(1)->setDecision(ExpenseApproval::DECISION_APPROVED)->setApprovedBy($approver)->setApprovedAt(new \DateTimeImmutable());
        $em->persist($approval);
        $em->flush();

        self::assertTrue($repository->hasUserApprovedAnyLevel($expense, $approver));
        self::assertFalse($repository->hasUserApprovedAnyLevel($expense, $otherUser));
    }
}
