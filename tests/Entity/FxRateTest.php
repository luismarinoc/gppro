<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\FxRate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FxRate::class)]
class FxRateTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $sut = new FxRate();

        self::assertNull($sut->getId());
        self::assertNull($sut->getDate());
        self::assertNull($sut->getIndicator());
        self::assertNull($sut->getRateValue());
        self::assertNull($sut->getModifiedAt());
    }

    public function testSetterAndGetter(): void
    {
        $sut = new FxRate();
        $date = new \DateTimeImmutable('2026-07-20');

        self::assertInstanceOf(FxRate::class, $sut->setDate($date));
        self::assertSame($date, $sut->getDate());

        self::assertInstanceOf(FxRate::class, $sut->setIndicator(FxRate::INDICATOR_USD));
        self::assertEquals(FxRate::INDICATOR_USD, $sut->getIndicator());

        self::assertInstanceOf(FxRate::class, $sut->setRateValue('970.123456'));
        self::assertEquals('970.123456', $sut->getRateValue());

        self::assertNull($sut->getModifiedAt());
        $sut->markAsModified();
        self::assertInstanceOf(\DateTimeImmutable::class, $sut->getModifiedAt());
    }

    public function testIndicatorConstantsAreDistinctMindicadorSlugs(): void
    {
        self::assertEquals('dolar', FxRate::INDICATOR_USD);
        self::assertEquals('uf', FxRate::INDICATOR_UF);
        self::assertEquals([FxRate::INDICATOR_USD, FxRate::INDICATOR_UF], FxRate::INDICATORS);
    }
}
