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
use App\Entity\Project;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\ExpenseRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ExpenseRepository::class)]
#[Group('integration')]
class ExpenseRepositoryTest extends AbstractRepositoryTestCase
{
    private function getRepository(): ExpenseRepository
    {
        /** @var ExpenseRepository $repository */
        $repository = $this->getEntityManager()->getRepository(Expense::class);

        return $repository;
    }

    private function createProject(): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Expense repository test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Expense repository test project ' . uniqid());
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
        $user->setUsername('expense-repo-test-' . $suffix);
        $user->setEmail('expense-repo-test-' . $suffix . '@example.com');
        $user->setPassword('irrelevant');
        $user->setEnabled(true);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createExpense(Project $project, ?User $createdBy = null): Expense
    {
        $expense = new Expense();
        $expense->setDescription('Expense ' . uniqid());
        $expense->setAmount(100000);
        $expense->setExpenseDate(new \DateTimeImmutable('today'));
        if (null !== $createdBy) {
            $expense->setCreatedBy($createdBy);
        }
        $expense->addAllocation((new ExpenseAllocation())->setProject($project)->setPercentage('100.00')->setAmountClp(100000));

        $this->getRepository()->saveExpense($expense);

        return $expense;
    }

    public function testSaveAndDeleteExpensePersistsAllocations(): void
    {
        $em = $this->getEntityManager();
        $repository = $this->getRepository();
        $project = $this->createProject();

        $expense = $this->createExpense($project);
        $id = $expense->getId();
        self::assertNotNull($id);

        $em->clear();

        $stored = $repository->find($id);
        self::assertNotNull($stored);
        self::assertCount(1, $stored->getAllocations());

        $repository->deleteExpense($stored);

        self::assertNull($repository->find($id));
    }

    public function testFindForListingFiltersByStatus(): void
    {
        $repository = $this->getRepository();
        $project = $this->createProject();
        $viewer = $this->createUser();

        $draft = $this->createExpense($project, $viewer);
        $submitted = $this->createExpense($project, $viewer);
        $submitted->submitForApproval(1);
        $repository->saveExpense($submitted);

        $draftResults = $repository->findForListing($viewer, Expense::STATUS_DRAFT);
        $draftIds = array_map(static fn (Expense $e): ?int => $e->getId(), $draftResults);

        self::assertContains($draft->getId(), $draftIds);
        self::assertNotContains($submitted->getId(), $draftIds);
    }

    public function testFindForListingIncludesExpenseOnTeamAccessibleProject(): void
    {
        $repository = $this->getRepository();
        $team = $this->createTeam();
        $viewer = $this->createUserInTeam($team);
        $creator = $this->createUser();
        $project = $this->createProjectWithTeam($team);

        $expense = $this->createExpense($project, $creator);

        $results = $repository->findForListing($viewer);
        $ids = array_map(static fn (Expense $e): ?int => $e->getId(), $results);

        self::assertContains($expense->getId(), $ids);
    }

    public function testFindForListingExcludesExpenseOnNonTeamProjectForStranger(): void
    {
        $repository = $this->getRepository();
        $team = $this->createTeam();
        $otherTeam = $this->createTeam();
        $viewer = $this->createUserInTeam($otherTeam);
        $creator = $this->createUser();
        $project = $this->createProjectWithTeam($team);

        $expense = $this->createExpense($project, $creator);

        $results = $repository->findForListing($viewer);
        $ids = array_map(static fn (Expense $e): ?int => $e->getId(), $results);

        self::assertNotContains($expense->getId(), $ids);
    }

    public function testFindForListingIncludesOwnExpenseOnNonTeamProject(): void
    {
        $repository = $this->getRepository();
        $team = $this->createTeam();
        $otherTeam = $this->createTeam();
        $creator = $this->createUserInTeam($otherTeam);
        $project = $this->createProjectWithTeam($team);

        $expense = $this->createExpense($project, $creator);

        $results = $repository->findForListing($creator);
        $ids = array_map(static fn (Expense $e): ?int => $e->getId(), $results);

        self::assertContains($expense->getId(), $ids);
    }

    public function testFindForListingReturnsExpenseOnceDespiteMultipleAllocationsWithPartialTeamMatch(): void
    {
        $repository = $this->getRepository();
        $team = $this->createTeam();
        $otherTeam = $this->createTeam();
        $viewer = $this->createUserInTeam($team);
        $creator = $this->createUser();
        $teamProject = $this->createProjectWithTeam($team);
        $otherProject = $this->createProjectWithTeam($otherTeam);

        $expense = new Expense();
        $expense->setDescription('Multi-allocation expense ' . uniqid());
        $expense->setAmount(100000);
        $expense->setExpenseDate(new \DateTimeImmutable('today'));
        $expense->setCreatedBy($creator);
        $expense->addAllocation((new ExpenseAllocation())->setProject($teamProject)->setPercentage('50.00')->setAmountClp(50000));
        $expense->addAllocation((new ExpenseAllocation())->setProject($otherProject)->setPercentage('50.00')->setAmountClp(50000));
        $repository->saveExpense($expense);

        $results = $repository->findForListing($viewer);
        $ids = array_map(static fn (Expense $e): ?int => $e->getId(), $results);

        self::assertCount(1, array_filter($ids, static fn (?int $id): bool => $id === $expense->getId()));
    }

    public function testFindForListingReturnsEveryExpenseForAdminRegardlessOfTeamOrCreator(): void
    {
        $repository = $this->getRepository();
        $team = $this->createTeam();
        $otherTeam = $this->createTeam();
        $admin = $this->createAdminUser();
        $creator = $this->createUserInTeam($otherTeam);
        $project = $this->createProjectWithTeam($team);

        $expense = $this->createExpense($project, $creator);

        $results = $repository->findForListing($admin);
        $ids = array_map(static fn (Expense $e): ?int => $e->getId(), $results);

        self::assertContains($expense->getId(), $ids);
    }

    private function createTeam(): Team
    {
        $em = $this->getEntityManager();

        $team = new Team('Expense repository test team ' . uniqid());
        $em->persist($team);
        $em->flush();

        return $team;
    }

    private function createUserInTeam(Team $team): User
    {
        $user = $this->createUser();
        $team->addUser($user);
        $this->getEntityManager()->flush();

        return $user;
    }

    private function createAdminUser(): User
    {
        $user = $this->createUser();
        $user->addRole(User::ROLE_SUPER_ADMIN);
        $this->getEntityManager()->flush();

        return $user;
    }

    private function createProjectWithTeam(Team $team): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Expense repository test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');

        $project = new Project();
        $project->setName('Expense repository test project ' . uniqid());
        $project->setCustomer($customer);
        // Doctrine only tracks owning-side ManyToMany changes on a
        // PersistentCollection - a freshly-created entity's collection stays
        // a plain ArrayCollection after its first flush, so the team must be
        // attached before the initial persist/flush, not after.
        $project->addTeam($team);

        $em->persist($customer);
        $em->persist($project);
        $em->flush();

        return $project;
    }

    public function testFindPendingForUserExcludesOwnExpenses(): void
    {
        $repository = $this->getRepository();
        $project = $this->createProject();
        $creator = $this->createUser();
        $otherUser = $this->createUser();

        $ownExpense = $this->createExpense($project, $creator);
        $ownExpense->submitForApproval(1);
        $repository->saveExpense($ownExpense);

        $othersExpense = $this->createExpense($project, $otherUser);
        $othersExpense->submitForApproval(1);
        $repository->saveExpense($othersExpense);

        $pending = $repository->findPendingForUser($creator);
        $pendingIds = array_map(static fn (Expense $e): ?int => $e->getId(), $pending);

        self::assertNotContains($ownExpense->getId(), $pendingIds);
        self::assertContains($othersExpense->getId(), $pendingIds);
    }

    public function testFindRecurringSourcesExcludesGeneratedCopies(): void
    {
        $repository = $this->getRepository();
        $project = $this->createProject();

        $source = $this->createExpense($project);
        $source->setRecurrence(Expense::RECURRENCE_MONTH);
        $repository->saveExpense($source);

        $copy = new Expense();
        $copy->setDescription('Generated copy');
        $copy->setAmount(100000);
        $copy->setExpenseDate(new \DateTimeImmutable('today'));
        $copy->setRecurrence(Expense::RECURRENCE_MONTH);
        $copy->setSourceExpense($source);
        $copy->setPeriodKey('2026-08');
        $repository->saveExpense($copy);

        $notRecurring = $this->createExpense($project);

        $sources = $repository->findRecurringSources();
        $sourceIds = array_map(static fn (Expense $e): ?int => $e->getId(), $sources);

        self::assertContains($source->getId(), $sourceIds);
        self::assertNotContains($copy->getId(), $sourceIds);
        self::assertNotContains($notRecurring->getId(), $sourceIds);
    }

    public function testFindGeneratedCopyReturnsExistingCopyForSourceAndPeriod(): void
    {
        $repository = $this->getRepository();
        $project = $this->createProject();

        $source = $this->createExpense($project);
        $source->setRecurrence(Expense::RECURRENCE_MONTH);
        $repository->saveExpense($source);

        $copy = new Expense();
        $copy->setDescription('Generated copy');
        $copy->setAmount(100000);
        $copy->setExpenseDate(new \DateTimeImmutable('today'));
        $copy->setRecurrence(Expense::RECURRENCE_MONTH);
        $copy->setSourceExpense($source);
        $copy->setPeriodKey('2026-08');
        $repository->saveExpense($copy);

        $found = $repository->findGeneratedCopy($source, '2026-08');
        self::assertNotNull($found);
        self::assertSame($copy->getId(), $found->getId());

        self::assertNull($repository->findGeneratedCopy($source, '2026-09'));
    }
}
