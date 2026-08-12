<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Expense;

/**
 * Per-source outcome of a {@see RecurringExpenseGenerator} run, mirroring
 * FxRateSyncStatus (src/FxRate/).
 */
enum ExpenseRecurrenceStatus
{
    /**
     * A new draft copy was created for the given source and period.
     */
    case GENERATED;

    /**
     * A copy already existed for this (source, period) - idempotent no-op.
     */
    case SKIPPED_EXISTING;

    /**
     * The source expense is no longer flagged as monthly-recurring.
     */
    case SKIPPED_NOT_RECURRING;
}
