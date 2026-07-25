<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\FxRate\FxRateSynchronizer;
use App\FxRate\FxRateSyncResult;
use App\FxRate\FxRateSyncStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fetches and persists daily USD/UF values from mindicador.cl.
 *
 * The Chilean calendar date is always resolved in `America/Santiago`, never
 * from the host/container timezone (the Dockerfile bakes `Europe/Berlin`),
 * so the injected {@see ClockInterface} MUST provide the current UTC instant
 * only — this command converts it to Santiago's calendar day itself.
 *
 * Exit codes:
 * - SUCCESS (0): every indicator either persisted a value or legitimately
 *   had no data for that date (weekend/holiday) — the daily no-data case is
 *   expected behavior, not an error.
 * - FAILURE (1): at least one indicator failed with a transport error,
 *   non-404 non-2xx status, or malformed JSON.
 * - INVALID (2): `--date` could not be parsed, or is a future Chilean
 *   calendar date.
 */
#[AsCommand(name: 'gppro:fx-rates:sync', description: 'Sync daily USD/UF values from mindicador.cl')]
final class FxRatesSyncCommand extends Command
{
    private const TIMEZONE = 'America/Santiago';
    private const DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private readonly FxRateSynchronizer $synchronizer,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Fetches USD ("dolar") and UF from mindicador.cl and persists them as (date, indicator) rows. Without --date, syncs today\'s Chilean calendar date (America/Santiago). Intended to run once or twice daily via a scheduled job; safe to re-run (idempotent unless --force is passed).')
            ->addOption('date', null, InputOption::VALUE_OPTIONAL, \sprintf('Chilean calendar date to sync, format %s (default: today in %s)', self::DATE_FORMAT, self::TIMEZONE), null)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing (date, indicator) row instead of leaving it untouched')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = $this->todayInSantiago();

        $dateOption = $input->getOption('date');
        if (null !== $dateOption) {
            if (!\is_string($dateOption)) {
                $io->error('Invalid --date: expected a string value.');

                return Command::INVALID;
            }

            $date = $this->parseDate($dateOption);
            if (null === $date) {
                $io->error(\sprintf('Invalid --date "%s": expected format %s.', $dateOption, self::DATE_FORMAT));

                return Command::INVALID;
            }
        } else {
            $date = $today;
        }

        if ($date > $today) {
            $io->error(\sprintf(
                'Invalid --date "%s": cannot sync a future Chilean calendar date (today in %s is %s).',
                $date->format(self::DATE_FORMAT),
                self::TIMEZONE,
                $today->format(self::DATE_FORMAT)
            ));

            return Command::INVALID;
        }

        $force = (bool) $input->getOption('force');

        $results = $this->synchronizer->sync($date, $force);

        $hasFailure = false;

        foreach ($results as $indicator => $result) {
            if ($result->isFailure()) {
                $hasFailure = true;
                $message = \sprintf('fx-rates sync failed for indicator "%s" on %s: %s', $indicator, $date->format(self::DATE_FORMAT), $result->errorMessage);
                $this->logger->error($message);
                $io->error($message);

                continue;
            }

            $message = $this->describeSuccess($indicator, $result, $date);
            $this->logger->info($message);
            $io->writeln($message);
        }

        if ($hasFailure) {
            return Command::FAILURE;
        }

        $io->success(\sprintf('fx-rates sync completed for %s.', $date->format(self::DATE_FORMAT)));

        return Command::SUCCESS;
    }

    /**
     * @param FxRateSyncResult $result a non-failure result (PERSISTED, SKIPPED_EXISTING, or SKIPPED_NO_DATA)
     */
    private function describeSuccess(string $indicator, FxRateSyncResult $result, \DateTimeImmutable $date): string
    {
        return match ($result->status) {
            FxRateSyncStatus::PERSISTED => \sprintf('fx-rates sync: persisted %s=%s for %s', $indicator, $result->value, $date->format(self::DATE_FORMAT)),
            FxRateSyncStatus::SKIPPED_EXISTING => \sprintf('fx-rates sync: skipped %s for %s (already exists, use --force to overwrite)', $indicator, $date->format(self::DATE_FORMAT)),
            FxRateSyncStatus::SKIPPED_NO_DATA => \sprintf('fx-rates sync: no data for %s on %s', $indicator, $date->format(self::DATE_FORMAT)),
            FxRateSyncStatus::FAILED => throw new \LogicException('describeSuccess() must not be called with a FAILED result'),
        };
    }

    private function todayInSantiago(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now())
            ->setTimezone(new \DateTimeZone(self::TIMEZONE))
            ->setTime(0, 0, 0)
        ;
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $value, new \DateTimeZone(self::TIMEZONE));

        if (false === $date || $date->format(self::DATE_FORMAT) !== $value) {
            return null;
        }

        return $date;
    }
}
