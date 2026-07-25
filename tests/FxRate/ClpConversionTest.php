<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\FxRate;

use App\FxRate\ClpConversion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClpConversion::class)]
class ClpConversionTest extends TestCase
{
    public function testIdentityCarriesClpVerbatimWithNoRate(): void
    {
        $sut = ClpConversion::identity('1500.0000');

        self::assertSame('1500.0000', $sut->sourceAmount);
        self::assertSame('CLP', $sut->sourceCurrency);
        self::assertNull($sut->rate);
        self::assertNull($sut->rateDate);
        self::assertSame('1500.0000', $sut->clpAmount);
        self::assertFalse($sut->isConverted());
    }

    public function testConvertedCarriesRateAndRateDateUsed(): void
    {
        $rateDate = new \DateTimeImmutable('2026-08-14');

        $sut = ClpConversion::converted('10.0000', 'USD', '960.000000', $rateDate, '9600.0000');

        self::assertSame('10.0000', $sut->sourceAmount);
        self::assertSame('USD', $sut->sourceCurrency);
        self::assertSame('960.000000', $sut->rate);
        self::assertSame($rateDate, $sut->rateDate);
        self::assertSame('9600.0000', $sut->clpAmount);
        self::assertTrue($sut->isConverted());
    }

    public function testInstanceIsReadonly(): void
    {
        $sut = ClpConversion::identity('100.0000');

        $this->expectException(\Error::class);

        // @phpstan-ignore-next-line property.readOnlyAssignNotInConstructor
        $sut->clpAmount = '200.0000';
    }
}
