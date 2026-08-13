<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Command;

use App\Command\ExpensesGenerateRecurringCommand;
use App\Entity\Customer;
use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Entity\Project;
use App\Expense\RecurringExpenseGenerator;
use App\Repository\ExpenseRepository;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ExpensesGenerateRecurringCommand::class)]
#[Group('integration')]
class ExpensesGenerateRecurringCommandTest extends KernelTestCase
{
    private Application $application;
    private EntityManagerInterface $entityManager;
    private ExpenseRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = self::bootKernel();

        /** @var Registry $doctrine */
        $doctrine = $kernel->getContainer()->get('doctrine');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $doctrine->getManager();
        $this->entityManager = $entityManager;

        /** @var ExpenseRepository $repository */
        $repository = $this->entityManager->getRepository(Expense::class);
        $this->repository = $repository;

        $this->application = new Application($kernel);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
    }

    private function addCommand(?MockClock $clock = null): Command
    {
        $generator = new RecurringExpenseGenerator($this->entityManager, $this->repository);
        $command = new ExpensesGenerateRecurringCommand($generator, $this->repository, new NullLogger(), $clock ?? new MockClock());
        $this->application->add($command);

        $found = $this->application->find('gppro:expenses:generate-recurring');
        self::assertInstanceOf(ExpensesGenerateRecurringCommand::class, $found);

        return $found;
    }

    private function createProject(): Project
    {
        $customer = new Customer('Recurring command test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $this->entityManager->persist($customer);

        $project = new Project();
        $project->setName('Recurring command test project ' . uniqid());
        $project->setCustomer($customer);
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    private function createRecurringSource(Project $project): Expense
    {
        $expense = new Expense();
        $expense->setDescription('Monthly rent');
        $expense->setAmount(100000);
        $expense->setExpenseDate(new \DateTimeImmutable('2026-07-05'));
        $expense->setRecurrence(Expense::RECURRENCE_MONTH);
        $expense->addAllocation((new ExpenseAllocation())->setProject($project)->setPercentage('100.00')->setAmountClp(100000));

        $this->repository->saveExpense($expense);

        return $expense;
    }

    public function testCommandName(): void
    {
        $command = $this->addCommand();

        self::assertSame('gppro:expenses:generate-recurring', $command->getName());
    }

    public function testGeneratesOneCopyPerRecurringSourceAndExitsSuccess(): void
    {
        $project = $this->createProject();
        $source = $this->createRecurringSource($project);

        $command = $this->addCommand();
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName(), '--period' => '2026-08']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $copy = $this->repository->findGeneratedCopy($source, '2026-08');
        self::assertNotNull($copy);
        self::assertSame(Expense::STATUS_DRAFT, $copy->getStatus());
    }

    public function testRunningTwiceForTheSamePeriodGeneratesOnlyOneCopyIdempotently(): void
    {
        $project = $this->createProject();
        $this->createRecurringSource($project);

        $command = $this->addCommand();

        $first = new CommandTester($command);
        $firstExit = $first->execute(['command' => $command->getName(), '--period' => '2026-08']);
        self::assertSame(Command::SUCCESS, $firstExit);

        $second = new CommandTester($command);
        $secondExit = $second->execute(['command' => $command->getName(), '--period' => '2026-08']);
        self::assertSame(Command::SUCCESS, $secondExit);

        $copies = $this->entityManager->getRepository(Expense::class)->findBy(['periodKey' => '2026-08']);
        self::assertCount(1, $copies);
        self::assertStringContainsString('skipped', $second->getDisplay());
    }

    public function testDryRunDoesNotPersistAnything(): void
    {
        $project = $this->createProject();
        $source = $this->createRecurringSource($project);

        $command = $this->addCommand();
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName(), '--period' => '2026-08', '--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('dry-run', $tester->getDisplay());
        self::assertNull($this->repository->findGeneratedCopy($source, '2026-08'));
    }

    public function testForceRegeneratesAnExistingDraftCopy(): void
    {
        $project = $this->createProject();
        $source = $this->createRecurringSource($project);

        $command = $this->addCommand();
        $first = new CommandTester($command);
        $first->execute(['command' => $command->getName(), '--period' => '2026-08']);

        $firstCopy = $this->repository->findGeneratedCopy($source, '2026-08');
        self::assertNotNull($firstCopy);
        $firstCopyId = $firstCopy->getId();

        $second = new CommandTester($command);
        $exitCode = $second->execute(['command' => $command->getName(), '--period' => '2026-08', '--force' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $copies = $this->entityManager->getRepository(Expense::class)->findBy(['periodKey' => '2026-08']);
        self::assertCount(1, $copies);
        self::assertNotSame($firstCopyId, $copies[0]->getId());
    }

    public function testInvalidPeriodFormatExitsInvalid(): void
    {
        $command = $this->addCommand();
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName(), '--period' => '2026-13']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Invalid --period', $tester->getDisplay());
    }

    public function testDefaultPeriodResolvesCurrentMonthInSantiagoNotHostTimezone(): void
    {
        // 2026-08-01 02:30:00 UTC is 2026-07-31 22:30:00 in Santiago (UTC-4) — still July there.
        $clock = new MockClock('2026-08-01 02:30:00', 'UTC');
        $project = $this->createProject();
        $source = $this->createRecurringSource($project);

        $command = $this->addCommand($clock);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName()]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($this->repository->findGeneratedCopy($source, '2026-07'));
        self::assertNull($this->repository->findGeneratedCopy($source, '2026-08'));
    }

    public function testNoRecurringSourcesExitsSuccess(): void
    {
        $command = $this->addCommand();
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName(), '--period' => '2026-08']);

        self::assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * Task 6.2: proves `UNIQ_GPPRO_EXPENSES_SOURCE_PERIOD (source_expense_id,
     * period_key)` — not just the application-level `findGeneratedCopy`
     * pre-check — is what actually enforces idempotency for a race window
     * two concurrent `generate-recurring` runs can hit: both processes can
     * independently observe "no copy yet" before either has committed, then
     * both attempt to insert. This test bypasses
     * `RecurringExpenseGenerator`'s own pre-check on purpose (it directly
     * persists a second, colliding `Expense` for the SAME
     * (source_expense_id, period_key) pair right after the first one was
     * generated) to prove the database constraint itself — the "final
     * guarantee under concurrency" `RecurringExpenseGenerator`'s own
     * docblock refers to — rejects the duplicate even when the application
     * guard is skipped.
     *
     * Genuine cross-connection concurrency (two real, separate DB sessions
     * racing at the same instant) cannot be exercised in this suite: DAMA
     * DoctrineTestBundle (`phpunit.xml.dist` bootstrap) wraps each test
     * method in one outer, never-committed transaction, so a second,
     * independently-opened DBAL connection would not see this test's
     * uncommitted fixtures at all and would silently miss the real
     * constraint (verified empirically — it also inserts `NULL` for
     * `source_expense_id`, and MySQL treats every `NULL` as distinct for
     * uniqueness purposes, masking the exact race this test needs to prove).
     * Exercising the constraint within the same connection, with the
     * application-level guard intentionally bypassed, is the reliable way to
     * prove the DB-level safety net under this test infrastructure.
     */
    public function testDatabaseUniqueIndexRejectsADuplicateSourcePeriodPairEvenWhenTheAppLevelPreCheckIsBypassed(): void
    {
        $project = $this->createProject();
        $source = $this->createRecurringSource($project);

        $generator = new RecurringExpenseGenerator($this->entityManager, $this->repository);
        $result = $generator->generateOne($source, '2026-08');
        self::assertSame(\App\Expense\ExpenseRecurrenceStatus::GENERATED, $result->status);

        $duplicate = new Expense();
        $duplicate->setDescription('Monthly rent');
        $duplicate->setAmount(100000);
        $duplicate->setExpenseDate(new \DateTimeImmutable('2026-08-01'));
        $duplicate->setRecurrence(Expense::RECURRENCE_MONTH);
        $duplicate->setSourceExpense($source);
        $duplicate->setPeriodKey('2026-08');
        $this->entityManager->persist($duplicate);

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }
}
