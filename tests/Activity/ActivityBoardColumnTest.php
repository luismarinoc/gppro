<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Activity;

use App\Activity\ActivityBoardCard;
use App\Activity\ActivityBoardColumn;
use App\Entity\Activity;
use App\Entity\ActivityBoardPriority;
use App\Entity\ActivityBoardState;
use App\Entity\ActivityBoardStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityBoardColumn::class)]
#[CoversClass(ActivityBoardCard::class)]
class ActivityBoardColumnTest extends TestCase
{
    private function createCard(string $name, ?ActivityBoardPriority $priority, ?string $dueDate): ActivityBoardCard
    {
        $activity = new Activity();
        $activity->setName($name);

        $state = new ActivityBoardState();
        $state->setActivity($activity);
        $state->setStatus(ActivityBoardStatus::TODO);
        $state->setPriority($priority);
        $state->setDueDate(null !== $dueDate ? new \DateTime($dueDate) : null);

        return new ActivityBoardCard($activity, $state);
    }

    public function testGetStatusReturnsTheStatusPassedToTheConstructor(): void
    {
        $column = new ActivityBoardColumn(ActivityBoardStatus::IN_REVIEW, []);

        self::assertSame(ActivityBoardStatus::IN_REVIEW, $column->getStatus());
    }

    public function testEmptyColumnHasNoCards(): void
    {
        $column = new ActivityBoardColumn(ActivityBoardStatus::TODO, []);

        self::assertSame([], $column->getCards());
        self::assertCount(0, $column->getCards());
    }

    public function testCardsAreOrderedByPriorityDescendingThenDueDateAscendingWithNullsLastThenNameAscending(): void
    {
        // Deliberately scrambled input order - the column must re-sort it.
        $low = $this->createCard('Zebra task', ActivityBoardPriority::LOW, '2026-09-01');
        $urgentNoDueDate = $this->createCard('Alpha task', ActivityBoardPriority::URGENT, null);
        $urgentEarlyDueDate = $this->createCard('Beta task', ActivityBoardPriority::URGENT, '2026-08-01');
        $urgentLateDueDate = $this->createCard('Gamma task', ActivityBoardPriority::URGENT, '2026-08-15');
        $noPriority = $this->createCard('Omega task', null, null);
        $noPrioritySameNameTieBreakA = $this->createCard('Zed task', null, null);

        $column = new ActivityBoardColumn(ActivityBoardStatus::TODO, [
            $low,
            $noPrioritySameNameTieBreakA,
            $urgentNoDueDate,
            $noPriority,
            $urgentLateDueDate,
            $urgentEarlyDueDate,
        ]);

        self::assertSame(
            [$urgentEarlyDueDate, $urgentLateDueDate, $urgentNoDueDate, $low, $noPriority, $noPrioritySameNameTieBreakA],
            $column->getCards()
        );
    }

    public function testCardsWithSamePriorityAndDueDateAreOrderedByNameAscending(): void
    {
        $b = $this->createCard('Bravo', ActivityBoardPriority::MEDIUM, '2026-08-01');
        $a = $this->createCard('Alpha', ActivityBoardPriority::MEDIUM, '2026-08-01');
        $c = $this->createCard('Charlie', ActivityBoardPriority::MEDIUM, '2026-08-01');

        $column = new ActivityBoardColumn(ActivityBoardStatus::TODO, [$b, $c, $a]);

        self::assertSame([$a, $b, $c], $column->getCards());
    }
}
