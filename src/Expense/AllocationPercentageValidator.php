<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Expense;

/**
 * Validates the sum of an expense's allocation percentages against the two
 * rules from the spec ("Allocate expense by percentage"): the sum must
 * never exceed 100% (draft) and must equal exactly 100% to submit.
 *
 * Percentages are compared as basis points (int, 0..10000) - see design D3 -
 * to avoid float rounding issues on DECIMAL(5,2) values.
 */
final class AllocationPercentageValidator
{
    private const MAX_BASIS_POINTS = 10000;

    /**
     * @param string[] $percentages decimal strings, e.g. "40.00"
     */
    public function isValidForDraft(array $percentages): bool
    {
        return $this->sumBasisPoints($percentages) <= self::MAX_BASIS_POINTS;
    }

    /**
     * @param string[] $percentages decimal strings, e.g. "40.00"
     */
    public function isValidForSubmit(array $percentages): bool
    {
        return self::MAX_BASIS_POINTS === $this->sumBasisPoints($percentages);
    }

    /**
     * @param string[] $percentages decimal strings, e.g. "40.00"
     */
    private function sumBasisPoints(array $percentages): int
    {
        $sum = 0;

        foreach ($percentages as $percentage) {
            $sum += (int) round(((float) $percentage) * 100);
        }

        return $sum;
    }
}
