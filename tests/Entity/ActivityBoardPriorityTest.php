<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\ActivityBoardPriority;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityBoardPriority::class)]
class ActivityBoardPriorityTest extends TestCase
{
    public function testSortWeightOrdersUrgentFirstAndLowLast(): void
    {
        self::assertSame(0, ActivityBoardPriority::sortWeight(ActivityBoardPriority::URGENT));
        self::assertSame(1, ActivityBoardPriority::sortWeight(ActivityBoardPriority::HIGH));
        self::assertSame(2, ActivityBoardPriority::sortWeight(ActivityBoardPriority::MEDIUM));
        self::assertSame(3, ActivityBoardPriority::sortWeight(ActivityBoardPriority::LOW));
    }

    public function testSortWeightSortsNoPriorityLastOfAll(): void
    {
        self::assertSame(4, ActivityBoardPriority::sortWeight(null));
    }
}
