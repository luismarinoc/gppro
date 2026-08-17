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
use App\Entity\Project;
use App\Expense\ExpenseRecurrenceStatus;
use App\Expense\RecurringExpenseGenerator;
use App\Repository\ExpenseRepository;
use App\Tests\Repository\AbstractRepositoryTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(RecurringExpenseGenerator::class)]
#[Group('integration')]
class RecurringExpenseGeneratorTest extends AbstractRepositoryTestCase
{
    private function getRepository(): ExpenseRepository
    {
        /** @var ExpenseRepository $repository */
        $repository = $this->getEntityManager()->getRepository(Expense::class);

        return $repository;
    }

    private function getSut(): RecurringExpenseGenerator
    {
        $em = $this->getEntityManager();
        \assert($em instanceof EntityManagerInterface);

        return new RecurringExpenseGenerator($em, $this->getRepository());
    }

    private function createProject(): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Recurring generator test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Recurring generator test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);
        $em->flush();

        return $project;
    }

    private function createRecurringSource(Project $projectA, Project $projectB): Expense
    {
        $expense = new Expense();
        $expense->setDescription('Monthly rent');
        $expense->setAmount(100000);
        $expense->setCurrency(Expense::CURRENCY_USD);
        $expense->setExpenseDate(new \DateTimeImmutable('2026-07-05'));
        $expense->setRecurrence(Expense::RECURRENCE_MONTH);
        $expense->setCategory(Expense::CATEGORY_RENT);
        $expense->addAllocation((new ExpenseAllocation())->setProject($projectA)->setPercentage('60.00')->setAmountClp(60000));
        $expense->addAllocation((new ExpenseAllocation())->setProject($projectB)->setPercentage('40.00')->setAmountClp(40000));

        $this->getRepository()->saveExpense($expense);

        return $expense;
    }

    public function testGeneratesNewDraftCopyWithTheSameAllocationSplit(): void
    {
        $projectA = $this->createProject();
        $projectB = $this->createProject();
        $source = $this->createRecurringSource($projectA, $projectB);

        $result = $this->getSut()->generateOne($source, '2026-08');

        self::assertSame(ExpenseRecurrenceStatus::GENERATED, $result->status);
        self::assertNotNull($result->generatedExpense);
        self::assertSame(Expense::STATUS_DRAFT, $result->generatedExpense->getStatus());
        self::assertSame(100000, $result->generatedExpense->getAmount());
        self::assertSame(Expense::CURRENCY_USD, $result->generatedExpense->getCurrency());
        self::assertSame('2026-08', $result->generatedExpense->getPeriodKey());
        self::assertSame($source->getId(), $result->generatedExpense->getSourceExpense()?->getId());
        self::assertSame(Expense::CATEGORY_RENT, $result->generatedExpense->getCategory());
        self::assertCount(2, $result->generatedExpense->getAllocations());

        $percentages = array_map(
            static fn (ExpenseAllocation $a): ?string => $a->getPercentage(),
            $result->generatedExpense->getAllocations()->toArray(),
        );
        self::assertContains('60.00', $percentages);
        self::assertContains('40.00', $percentages);
    }

    public function testGeneratesCopyWithNullCategoryWhenSourceHasNoCategory(): void
    {
        $projectA = $this->createProject();
        $projectB = $this->createProject();
        $source = $this->createRecurringSource($projectA, $projectB);
        $source->setCategory(null);
        $this->getRepository()->saveExpense($source);

        $result = $this->getSut()->generateOne($source, '2026-08');

        self::assertSame(ExpenseRecurrenceStatus::GENERATED, $result->status);
        self::assertNotNull($result->generatedExpense);
        self::assertNull($result->generatedExpense->getCategory());
    }

    public function testSkipsWhenACopyAlreadyExistsForThePeriod(): void
    {
        $projectA = $this->createProject();
        $projectB = $this->createProject();
        $source = $this->createRecurringSource($projectA, $projectB);
        $first = $this->getSut()->generateOne($source, '2026-08');
        self::assertSame(ExpenseRecurrenceStatus::GENERATED, $first->status);

        $second = $this->getSut()->generateOne($source, '2026-08');

        self::assertSame(ExpenseRecurrenceStatus::SKIPPED_EXISTING, $second->status);
        self::assertNull($second->generatedExpense);
    }

    public function testSkipsWhenSourceIsNotRecurring(): void
    {
        $projectA = $this->createProject();
        $projectB = $this->createProject();
        $source = $this->createRecurringSource($projectA, $projectB);
        $source->setRecurrence(null);
        $this->getRepository()->saveExpense($source);

        $result = $this->getSut()->generateOne($source, '2026-08');

        self::assertSame(ExpenseRecurrenceStatus::SKIPPED_NOT_RECURRING, $result->status);
        self::assertNull($result->generatedExpense);
    }

    public function testGenerateForPeriodProcessesEveryRecurringSource(): void
    {
        $projectA = $this->createProject();
        $projectB = $this->createProject();
        $sourceOne = $this->createRecurringSource($projectA, $projectB);
        $sourceTwo = $this->createRecurringSource($projectA, $projectB);

        $results = $this->getSut()->generateForPeriod('2026-09');

        self::assertCount(2, $results);
        $statuses = array_map(static fn ($r) => $r->status, $results);
        self::assertSame([ExpenseRecurrenceStatus::GENERATED, ExpenseRecurrenceStatus::GENERATED], $statuses);

        $again = $this->getSut()->generateForPeriod('2026-09');
        $againStatuses = array_map(static fn ($r) => $r->status, $again);
        self::assertSame([ExpenseRecurrenceStatus::SKIPPED_EXISTING, ExpenseRecurrenceStatus::SKIPPED_EXISTING], $againStatuses);

        unset($sourceOne, $sourceTwo);
    }
}
