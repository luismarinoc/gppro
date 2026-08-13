<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\Entity\Expense;
use App\Expense\ExpenseRecurrenceStatus;
use App\Expense\RecurringExpenseGenerator;
use App\Repository\ExpenseRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates one new draft {@see Expense} copy per period for every
 * monthly-recurring source (spec: "Generate monthly recurring copies").
 *
 * Modeled exactly on {@see FxRatesSyncCommand}: same option/exit-code shape,
 * same `America/Santiago`-first clock handling. The actual clone/split
 * logic stays in {@see RecurringExpenseGenerator} (PR3) — this command only
 * orchestrates: it resolves the period, loops recurring sources via the
 * already-public {@see ExpenseRepository} accessors, and reports outcomes.
 *
 * `--force` never bypasses the spec's idempotency MUST for a copy that has
 * left `draft` (submitted/approved/rejected): only a still-draft previously
 * generated copy for the exact same (source, period) is discarded and
 * regenerated. `--dry-run` never persists — it previews GENERATED/SKIPPED
 * outcomes using the same read-only lookups, without invoking the
 * generator at all.
 *
 * Exit codes:
 * - SUCCESS (0): every source was generated or legitimately skipped
 *   (already exists, or not force-overridable).
 * - FAILURE (1): at least one source failed with an unexpected persistence
 *   error.
 * - INVALID (2): `--period` could not be parsed as `Y-m`.
 */
#[AsCommand(name: 'gppro:expenses:generate-recurring', description: 'Generate this period\'s draft copies for every monthly-recurring expense')]
final class ExpensesGenerateRecurringCommand extends Command
{
    private const TIMEZONE = 'America/Santiago';
    private const PERIOD_FORMAT = 'Y-m';

    public function __construct(
        private readonly RecurringExpenseGenerator $generator,
        private readonly ExpenseRepository $repository,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Generates one new draft Expense copy per monthly-recurring source for the given period. Without --period, uses the current Chilean calendar month (America/Santiago). Idempotent per (source, period) unless --force is passed. Intended to run once a month via a scheduled job.')
            ->addOption('period', null, InputOption::VALUE_OPTIONAL, \sprintf('Period to generate copies for, format %s (default: current month in %s)', self::PERIOD_FORMAT, self::TIMEZONE), null)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Discard and regenerate a previously generated copy for this period, but only while it is still in draft status')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be generated without persisting anything')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $periodOption = $input->getOption('period');
        if (null !== $periodOption) {
            if (!\is_string($periodOption)) {
                $io->error('Invalid --period: expected a string value.');

                return Command::INVALID;
            }

            $period = $this->parsePeriod($periodOption);
            if (null === $period) {
                $io->error(\sprintf('Invalid --period "%s": expected format %s.', $periodOption, self::PERIOD_FORMAT));

                return Command::INVALID;
            }
        } else {
            $period = $this->currentPeriodInSantiago();
        }

        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');

        $sources = $this->repository->findRecurringSources();

        if ([] === $sources) {
            $io->success(\sprintf('expenses recurring generation: no recurring sources found for %s.', $period));

            return Command::SUCCESS;
        }

        $hasFailure = false;
        $generatedCount = 0;
        $skippedCount = 0;

        foreach ($sources as $source) {
            $existing = $this->repository->findGeneratedCopy($source, $period);

            if (null !== $existing && $force && $existing->isEditable()) {
                if ($dryRun) {
                    $existing = null;
                } else {
                    $this->repository->deleteExpense($existing);
                    $existing = null;
                }
            }

            $sourceId = $source->getId() ?? 0;

            if ($dryRun) {
                $message = null === $existing
                    ? \sprintf('[dry-run] expenses recurring generation: would generate a copy of source #%d for %s', $sourceId, $period)
                    : \sprintf('[dry-run] expenses recurring generation: source #%d already has a copy for %s (skipped)', $sourceId, $period);

                null === $existing ? ++$generatedCount : ++$skippedCount;
                $this->logger->info($message);
                $io->writeln($message);

                continue;
            }

            try {
                $result = $this->generator->generateOne($source, $period);
            } catch (\Throwable $exception) {
                $hasFailure = true;
                $message = \sprintf('expenses recurring generation failed for source #%d for %s: %s', $sourceId, $period, $exception->getMessage());
                $this->logger->error($message);
                $io->error($message);

                continue;
            }

            $message = $this->describeResult($sourceId, $result->status, $period);
            ExpenseRecurrenceStatus::GENERATED === $result->status ? ++$generatedCount : ++$skippedCount;
            $this->logger->info($message);
            $io->writeln($message);
        }

        if ($hasFailure) {
            return Command::FAILURE;
        }

        $io->success(\sprintf('expenses recurring generation completed for %s: %d generated, %d skipped.', $period, $generatedCount, $skippedCount));

        return Command::SUCCESS;
    }

    private function describeResult(int $sourceId, ExpenseRecurrenceStatus $status, string $period): string
    {
        return match ($status) {
            ExpenseRecurrenceStatus::GENERATED => \sprintf('expenses recurring generation: generated a copy of source #%d for %s', $sourceId, $period),
            ExpenseRecurrenceStatus::SKIPPED_EXISTING => \sprintf('expenses recurring generation: skipped source #%d for %s (already exists, use --force to regenerate a draft copy)', $sourceId, $period),
            ExpenseRecurrenceStatus::SKIPPED_NOT_RECURRING => \sprintf('expenses recurring generation: skipped source #%d for %s (no longer monthly-recurring)', $sourceId, $period),
        };
    }

    private function currentPeriodInSantiago(): string
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now())
            ->setTimezone(new \DateTimeZone(self::TIMEZONE))
            ->format(self::PERIOD_FORMAT)
        ;
    }

    private function parsePeriod(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!' . self::PERIOD_FORMAT, $value, new \DateTimeZone(self::TIMEZONE));

        if (false === $date || $date->format(self::PERIOD_FORMAT) !== $value) {
            return null;
        }

        return $value;
    }
}
