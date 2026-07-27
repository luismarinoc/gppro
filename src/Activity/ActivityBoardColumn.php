<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Activity;

use App\Entity\ActivityBoardPriority;
use App\Entity\ActivityBoardStatus;

/**
 * One board column (status) with its cards pre-sorted for display:
 * priority weight ascending (Urgent first, no priority last - see
 * ActivityBoardPriority::sortWeight()), then due date ascending with null
 * due dates last, then activity name ascending.
 */
final class ActivityBoardColumn
{
    /**
     * @var ActivityBoardCard[]
     */
    private readonly array $cards;

    /**
     * @param ActivityBoardCard[] $cards
     */
    public function __construct(
        private readonly ActivityBoardStatus $status,
        array $cards
    ) {
        usort($cards, self::compare(...));
        $this->cards = $cards;
    }

    public function getStatus(): ActivityBoardStatus
    {
        return $this->status;
    }

    /**
     * @return ActivityBoardCard[]
     */
    public function getCards(): array
    {
        return $this->cards;
    }

    private static function compare(ActivityBoardCard $a, ActivityBoardCard $b): int
    {
        $weight = ActivityBoardPriority::sortWeight($a->getPriority()) <=> ActivityBoardPriority::sortWeight($b->getPriority());
        if (0 !== $weight) {
            return $weight;
        }

        $dueDateComparison = self::compareDueDates($a->getDueDate(), $b->getDueDate());
        if (0 !== $dueDateComparison) {
            return $dueDateComparison;
        }

        return ($a->getActivity()->getName() ?? '') <=> ($b->getActivity()->getName() ?? '');
    }

    private static function compareDueDates(?\DateTime $a, ?\DateTime $b): int
    {
        if (null === $a && null === $b) {
            return 0;
        }

        if (null === $a) {
            return 1;
        }

        if (null === $b) {
            return -1;
        }

        return $a <=> $b;
    }
}
