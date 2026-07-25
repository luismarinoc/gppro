<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Milestone;

use App\Entity\Milestone;
use App\FxRate\ClpConverter;

/**
 * Folds an already-loaded list of milestones into a single CLP total.
 *
 * Does not query the database itself - the caller is expected to have
 * loaded the milestone list already (e.g. via MilestoneRepository::findByProject()).
 */
final class MilestoneTotalCalculator
{
    public function __construct(private readonly ClpConverter $converter)
    {
    }

    /**
     * @param iterable<Milestone> $milestones
     */
    public function calculate(iterable $milestones): MilestoneTotal
    {
        $total = 0.0;
        $convertedCount = 0;
        $excludedCount = 0;

        foreach ($milestones as $milestone) {
            $value = $milestone->getValue();
            $currency = $milestone->getCurrency();

            if (null === $value || null === $currency) {
                continue;
            }

            $clpAmount = $this->converter->toClp($value, $currency, $milestone->getDueDate());

            if (null === $clpAmount) {
                ++$excludedCount;

                continue;
            }

            $total += (float) $clpAmount;
            ++$convertedCount;
        }

        return MilestoneTotal::create(
            $convertedCount > 0 ? number_format($total, 4, '.', '') : null,
            $convertedCount,
            $excludedCount,
        );
    }
}
