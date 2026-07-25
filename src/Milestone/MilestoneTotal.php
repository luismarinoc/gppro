<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Milestone;

/**
 * Outcome of folding a project's milestones into a single CLP total.
 *
 * Milestones without a value/currency are simply not valued and are neither
 * converted nor excluded. Only valued milestones that could not be converted
 * (no usable FX rate) count toward `excludedCount`.
 */
final class MilestoneTotal
{
    private function __construct(
        public readonly ?string $total,
        public readonly int $convertedCount,
        public readonly int $excludedCount,
    ) {
    }

    public static function create(?string $total, int $convertedCount, int $excludedCount): self
    {
        return new self($total, $convertedCount, $excludedCount);
    }

    /**
     * True when at least one valued milestone could not be converted to CLP.
     */
    public function isPartial(): bool
    {
        return $this->excludedCount > 0;
    }

    /**
     * True when at least one milestone contributed to the total.
     */
    public function hasTotal(): bool
    {
        return $this->convertedCount > 0;
    }
}
