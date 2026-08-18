<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseAllocation;

/**
 * Writes every {@see ExpenseAllocation::amountClp} of an {@see Expense},
 * composing {@see ExpenseClpAmountResolver} + {@see AllocationSplitter}
 * (design D1). Replaces the former private
 * `ExpenseController::recalculateAllocationAmounts()`.
 *
 * `applyAmount()` is a reuse seam for callers that already resolved the CLP
 * amount themselves (design D4: submit converts once) - it performs the
 * split/write step without a second conversion.
 */
final class ExpenseAllocationAmountUpdater
{
    public function __construct(
        private readonly ExpenseClpAmountResolver $resolver,
        private readonly AllocationSplitter $splitter,
    ) {
    }

    /**
     * Converts and splits. Returns `false` and clears every allocation's
     * `amountClp` to `null` (overwriting any stale prior value, design D2)
     * when the expense currency is not convertible right now.
     */
    public function apply(Expense $expense): bool
    {
        $clpAmount = $this->resolver->toClp($expense);

        if (null === $clpAmount) {
            foreach ($expense->getAllocations() as $allocation) {
                $allocation->setAmountClp(null);
            }

            return false;
        }

        $this->applyAmount($expense, $clpAmount);

        return true;
    }

    /**
     * Splits an already-resolved CLP amount across every allocation. Used
     * by {@see ExpenseApprovalService::submit()} so the freeze converts
     * only once.
     */
    public function applyAmount(Expense $expense, int $clpAmount): void
    {
        $allocations = $expense->getAllocations()->toArray();
        $basisPoints = array_map(
            static fn (ExpenseAllocation $allocation): int => (int) round(((float) ($allocation->getPercentage() ?? '0')) * 100),
            $allocations
        );
        $shares = $this->splitter->split($clpAmount, $basisPoints);

        foreach ($allocations as $index => $allocation) {
            $allocation->setAmountClp($shares[$index]);
        }
    }
}
