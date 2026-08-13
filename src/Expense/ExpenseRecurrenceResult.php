<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Expense;

use App\Entity\Expense;

/**
 * Outcome of generating (or skipping) a recurring copy of one source
 * expense for a given period, mirroring FxRateSyncResult (src/FxRate/).
 */
final class ExpenseRecurrenceResult
{
    private function __construct(
        public readonly Expense $sourceExpense,
        public readonly ExpenseRecurrenceStatus $status,
        public readonly ?Expense $generatedExpense = null,
    ) {
    }

    public static function generated(Expense $sourceExpense, Expense $generatedExpense): self
    {
        return new self($sourceExpense, ExpenseRecurrenceStatus::GENERATED, $generatedExpense);
    }

    public static function skippedExisting(Expense $sourceExpense): self
    {
        return new self($sourceExpense, ExpenseRecurrenceStatus::SKIPPED_EXISTING);
    }

    public static function skippedNotRecurring(Expense $sourceExpense): self
    {
        return new self($sourceExpense, ExpenseRecurrenceStatus::SKIPPED_NOT_RECURRING);
    }
}
