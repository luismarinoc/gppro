<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Expense;

/**
 * Splits a whole-CLP expense amount into per-allocation shares using basis
 * points (0..10000 = 0.00%..100.00%, see design D3). Integer arithmetic
 * only - no floats, no bcmath. The remainder from truncation is assigned
 * to the last share so the parts always sum back to the original amount.
 */
final class AllocationSplitter
{
    private const TOTAL_BASIS_POINTS = 10000;

    /**
     * @param int[] $basisPoints one entry per allocation share, 0..10000 each
     *
     * @return int[] CLP amounts, same order and count as $basisPoints, summing to $amountClp
     */
    public function split(int $amountClp, array $basisPoints): array
    {
        $shares = [];
        $allocated = 0;
        $lastIndex = \count($basisPoints) - 1;

        foreach ($basisPoints as $index => $bp) {
            if ($index === $lastIndex) {
                $shares[] = $amountClp - $allocated;

                continue;
            }

            $share = intdiv($amountClp * $bp, self::TOTAL_BASIS_POINTS);
            $shares[] = $share;
            $allocated += $share;
        }

        return $shares;
    }
}
