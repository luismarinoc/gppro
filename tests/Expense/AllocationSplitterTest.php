<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Expense;

use App\Expense\AllocationSplitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllocationSplitter::class)]
class AllocationSplitterTest extends TestCase
{
    public function testTwoWaySplitDividesExactly(): void
    {
        $sut = new AllocationSplitter();

        // 40.00% / 60.00% of 100.000 CLP -> 4000bp/6000bp, exact division.
        $result = $sut->split(100_000, [4000, 6000]);

        self::assertSame([40_000, 60_000], $result);
    }

    public function testThreeWaySplitPutsRemainderOnLast(): void
    {
        $sut = new AllocationSplitter();

        // 33.33% x3 of 100 CLP -> 3333bp each; 33+33+34 with remainder on the last share.
        $result = $sut->split(100, [3333, 3333, 3334]);

        self::assertSame([33, 33, 34], $result);
        self::assertSame(100, array_sum($result));
    }

    public function testSingleShareReceivesTheFullAmount(): void
    {
        $sut = new AllocationSplitter();

        $result = $sut->split(50_000, [10000]);

        self::assertSame([50_000], $result);
    }
}
