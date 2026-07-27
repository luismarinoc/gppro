<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

/**
 * One (customer, project, type) group of the invoice archive totals summary.
 *
 * Milestone amounts and timesheet amounts are semantically different kinds
 * of money (CLP-converted vs. raw per-invoice-currency rate sum) and are
 * therefore never merged into the same row - see InvoiceHistorySummarizer.
 */
final class InvoiceHistorySummaryRow
{
    public const TYPE_MILESTONE = 'milestone';
    public const TYPE_TIMESHEET = 'timesheet';

    private function __construct(
        public readonly string $customerName,
        public readonly string $projectName,
        public readonly string $type,
        public readonly int $invoiceCount,
        public readonly ?string $amount,
        public readonly string $currency,
        public readonly ?int $durationSeconds,
        public readonly bool $isPartial,
        public readonly int $excludedCount,
    ) {
    }

    public static function milestone(string $customerName, string $projectName, int $invoiceCount, ?string $amount, bool $isPartial, int $excludedCount): self
    {
        return new self($customerName, $projectName, self::TYPE_MILESTONE, $invoiceCount, $amount, 'CLP', null, $isPartial, $excludedCount);
    }

    public static function timesheet(string $customerName, string $projectName, string $currency, int $invoiceCount, ?string $amount, int $durationSeconds): self
    {
        return new self($customerName, $projectName, self::TYPE_TIMESHEET, $invoiceCount, $amount, $currency, $durationSeconds, false, 0);
    }

    public function isMilestone(): bool
    {
        return self::TYPE_MILESTONE === $this->type;
    }

    public function isTimesheet(): bool
    {
        return self::TYPE_TIMESHEET === $this->type;
    }
}
