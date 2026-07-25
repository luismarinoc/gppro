<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Milestone;

use App\Entity\Milestone;
use App\FxRate\ClpConverter;
use App\Milestone\MilestoneTotal;
use App\Milestone\MilestoneTotalCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MilestoneTotalCalculator::class)]
#[CoversClass(MilestoneTotal::class)]
class MilestoneTotalCalculatorTest extends TestCase
{
    private function makeMilestone(?string $value, ?string $currency): Milestone
    {
        $milestone = new Milestone();
        $milestone->setName('m');
        $milestone->setValue($value);
        $milestone->setCurrency($currency);

        return $milestone;
    }

    public function testAllConvertibleMilestonesSumToTotal(): void
    {
        $converter = $this->createMock(ClpConverter::class);
        $converter->method('toClp')->willReturnMap([
            ['1000.0000', 'CLP', null, '1000.0000'],
            ['10.0000', 'USD', null, '9600.0000'],
        ]);

        $milestones = [
            $this->makeMilestone('1000.0000', 'CLP'),
            $this->makeMilestone('10.0000', 'USD'),
        ];

        $sut = new MilestoneTotalCalculator($converter);
        $result = $sut->calculate($milestones);

        self::assertSame('10600.0000', $result->total);
        self::assertSame(2, $result->convertedCount);
        self::assertSame(0, $result->excludedCount);
        self::assertFalse($result->isPartial());
        self::assertTrue($result->hasTotal());
    }

    public function testSomeExcludedMilestonesAreCountedAndFlaggedPartial(): void
    {
        $converter = $this->createMock(ClpConverter::class);
        $converter->method('toClp')->willReturnMap([
            ['1000.0000', 'CLP', null, '1000.0000'],
            ['5.0000', 'USD', null, null],
        ]);

        $milestones = [
            $this->makeMilestone('1000.0000', 'CLP'),
            $this->makeMilestone('5.0000', 'USD'),
        ];

        $sut = new MilestoneTotalCalculator($converter);
        $result = $sut->calculate($milestones);

        self::assertSame('1000.0000', $result->total);
        self::assertSame(1, $result->convertedCount);
        self::assertSame(1, $result->excludedCount);
        self::assertTrue($result->isPartial());
        self::assertTrue($result->hasTotal());
    }

    public function testAllExcludedMilestonesProduceNoTotalButStaysPartial(): void
    {
        $converter = $this->createMock(ClpConverter::class);
        $converter->method('toClp')->willReturn(null);

        $milestones = [
            $this->makeMilestone('5.0000', 'USD'),
            $this->makeMilestone('2.0000', 'CLF'),
        ];

        $sut = new MilestoneTotalCalculator($converter);
        $result = $sut->calculate($milestones);

        self::assertNull($result->total);
        self::assertSame(0, $result->convertedCount);
        self::assertSame(2, $result->excludedCount);
        self::assertTrue($result->isPartial());
        self::assertFalse($result->hasTotal());
    }

    public function testEmptyMilestoneListProducesNoTotalAndNotPartial(): void
    {
        $converter = $this->createMock(ClpConverter::class);
        $converter->expects(self::never())->method('toClp');

        $sut = new MilestoneTotalCalculator($converter);
        $result = $sut->calculate([]);

        self::assertNull($result->total);
        self::assertSame(0, $result->convertedCount);
        self::assertSame(0, $result->excludedCount);
        self::assertFalse($result->isPartial());
        self::assertFalse($result->hasTotal());
    }

    public function testMilestonesWithoutValueAreIgnoredAndNotCountedExcluded(): void
    {
        $converter = $this->createMock(ClpConverter::class);
        $converter->expects(self::never())->method('toClp');

        $milestones = [
            $this->makeMilestone(null, null),
            $this->makeMilestone(null, null),
        ];

        $sut = new MilestoneTotalCalculator($converter);
        $result = $sut->calculate($milestones);

        self::assertNull($result->total);
        self::assertSame(0, $result->convertedCount);
        self::assertSame(0, $result->excludedCount);
        self::assertFalse($result->isPartial());
        self::assertFalse($result->hasTotal());
    }

    public function testMixedCurrencyMilestonesSumCorrectly(): void
    {
        $converter = $this->createMock(ClpConverter::class);
        $converter->method('toClp')->willReturnMap([
            ['500.0000', 'CLP', null, '500.0000'],
            ['10.0000', 'USD', null, '9600.0000'],
            ['2.0000', 'CLF', null, '78000.0000'],
        ]);

        $milestones = [
            $this->makeMilestone('500.0000', 'CLP'),
            $this->makeMilestone('10.0000', 'USD'),
            $this->makeMilestone('2.0000', 'CLF'),
            $this->makeMilestone(null, null),
        ];

        $sut = new MilestoneTotalCalculator($converter);
        $result = $sut->calculate($milestones);

        self::assertSame('88100.0000', $result->total);
        self::assertSame(3, $result->convertedCount);
        self::assertSame(0, $result->excludedCount);
    }
}
