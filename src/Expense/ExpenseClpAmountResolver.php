<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Expense;

use App\Entity\Expense;
use App\FxRate\ClpConverter;

/**
 * Converts an {@see Expense}'s raw amount to CLP via {@see ClpConverter},
 * the single conversion seam between the Expense entity and the currency
 * subsystem (design D1). Never guesses: returns `null` when no rate is
 * available for the expense's currency/date, mirroring
 * `InvoiceableMilestoneFinder::isConvertible()`.
 */
final class ExpenseClpAmountResolver
{
    public function __construct(private readonly ClpConverter $converter)
    {
    }

    /**
     * Rounds the scale-4 decimal CLP string to the integer `amount_clp`
     * stores. `null` when the expense has no amount or no rate is
     * available at or before its `expenseDate`.
     */
    public function toClp(Expense $expense): ?int
    {
        $amount = $expense->getAmount();

        if (null === $amount) {
            return null;
        }

        $conversion = $this->converter->convert((string) $amount, $expense->getCurrency(), $expense->getExpenseDate());

        return null !== $conversion ? (int) round((float) $conversion->clpAmount) : null;
    }

    public function isConvertible(Expense $expense): bool
    {
        return null !== $this->toClp($expense);
    }
}
