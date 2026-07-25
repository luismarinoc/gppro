<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\FxRate;

use App\Entity\FxRate;
use App\FxRate\ClpConversion;
use App\FxRate\ClpConverter;
use App\Repository\FxRateRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClpConverter::class)]
#[CoversClass(ClpConversion::class)]
class ClpConverterTest extends TestCase
{
    private function makeFxRate(string $date, string $indicator, string $rateValue): FxRate
    {
        $fxRate = new FxRate();
        $fxRate->setDate(new \DateTimeImmutable($date));
        $fxRate->setIndicator($indicator);
        $fxRate->setRateValue($rateValue);

        return $fxRate;
    }

    public function testClpConvertsToIdentityWithoutTouchingRepository(): void
    {
        $repository = $this->createMock(FxRateRepository::class);
        $repository->expects(self::never())->method('findLatestOnOrBefore');
        $repository->expects(self::never())->method('findLatest');

        $sut = new ClpConverter($repository);

        $result = $sut->convert('1500.0000', 'CLP', new \DateTimeImmutable('2026-08-14'));

        self::assertNotNull($result);
        self::assertNull($result->rate);
        self::assertNull($result->rateDate);
        self::assertFalse($result->isConverted());
        self::assertSame('1500.0000', $result->clpAmount);

        self::assertSame('1500.0000', $sut->toClp('1500.0000', 'CLP', new \DateTimeImmutable('2026-08-14')));
    }

    public function testUsdConvertsViaDolarIndicatorUsingFindLatestOnOrBefore(): void
    {
        $fxRate = $this->makeFxRate('2026-08-14', FxRate::INDICATOR_USD, '960.000000');

        $repository = $this->createMock(FxRateRepository::class);
        $repository->expects(self::once())
            ->method('findLatestOnOrBefore')
            ->with(FxRate::INDICATOR_USD, self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn($fxRate);

        $sut = new ClpConverter($repository);
        $date = new \DateTimeImmutable('2026-08-14');

        $result = $sut->convert('10.0000', 'USD', $date);

        self::assertNotNull($result);
        self::assertSame('960.000000', $result->rate);
        self::assertSame('9600.0000', $result->clpAmount);
        self::assertTrue($result->isConverted());
    }

    public function testClfConvertsViaUfIndicatorUsingFindLatestOnOrBefore(): void
    {
        $fxRate = $this->makeFxRate('2026-08-14', FxRate::INDICATOR_UF, '39000.000000');

        $repository = $this->createMock(FxRateRepository::class);
        $repository->expects(self::once())
            ->method('findLatestOnOrBefore')
            ->with(FxRate::INDICATOR_UF, self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn($fxRate);

        $sut = new ClpConverter($repository);

        $result = $sut->convert('2.0000', 'CLF', new \DateTimeImmutable('2026-08-14'));

        self::assertNotNull($result);
        self::assertSame('78000.0000', $result->clpAmount);
    }

    public function testNullDateUsesFindLatestInsteadOfFindLatestOnOrBefore(): void
    {
        $fxRate = $this->makeFxRate('2026-08-20', FxRate::INDICATOR_USD, '970.000000');

        $repository = $this->createMock(FxRateRepository::class);
        $repository->expects(self::never())->method('findLatestOnOrBefore');
        $repository->expects(self::once())
            ->method('findLatest')
            ->with(FxRate::INDICATOR_USD)
            ->willReturn($fxRate);

        $sut = new ClpConverter($repository);

        $result = $sut->convert('1.0000', 'USD', null);

        self::assertNotNull($result);
        self::assertSame('970.000000', $result->rate);
    }

    public function testRateDateReflectsReturnedRowNotRequestedDateOnWeekendGap(): void
    {
        $friday = $this->makeFxRate('2026-08-14', FxRate::INDICATOR_USD, '960.000000');

        $repository = $this->createMock(FxRateRepository::class);
        $repository->method('findLatestOnOrBefore')->willReturn($friday);

        $sut = new ClpConverter($repository);

        $result = $sut->convert('10.0000', 'USD', new \DateTimeImmutable('2026-08-16'));

        self::assertNotNull($result);
        self::assertEquals(new \DateTimeImmutable('2026-08-14'), $result->rateDate);
    }

    public function testNoRateAvailableReturnsNullForConvertAndToClp(): void
    {
        $repository = $this->createMock(FxRateRepository::class);
        $repository->method('findLatestOnOrBefore')->willReturn(null);

        $sut = new ClpConverter($repository);
        $date = new \DateTimeImmutable('2026-08-14');

        self::assertNull($sut->convert('10.0000', 'USD', $date));
        self::assertNull($sut->toClp('10.0000', 'USD', $date));
    }

    public function testUnsupportedCurrencyReturnsNullWithoutCallingRepository(): void
    {
        $repository = $this->createMock(FxRateRepository::class);
        $repository->expects(self::never())->method('findLatestOnOrBefore');
        $repository->expects(self::never())->method('findLatest');

        $sut = new ClpConverter($repository);
        $date = new \DateTimeImmutable('2026-08-14');

        self::assertNull($sut->convert('10.0000', 'EUR', $date));
        self::assertNull($sut->toClp('10.0000', 'EUR', $date));
    }

    public function testRepeatedLookupForSameIndicatorAndDateIsMemoized(): void
    {
        $fxRate = $this->makeFxRate('2026-08-14', FxRate::INDICATOR_USD, '960.000000');

        $repository = $this->createMock(FxRateRepository::class);
        $repository->expects(self::once())
            ->method('findLatestOnOrBefore')
            ->willReturn($fxRate);

        $sut = new ClpConverter($repository);
        $date = new \DateTimeImmutable('2026-08-14');

        $first = $sut->convert('10.0000', 'USD', $date);
        $second = $sut->convert('20.0000', 'USD', $date);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame('9600.0000', $first->clpAmount);
        self::assertSame('19200.0000', $second->clpAmount);
    }

    public function testToClpDelegatesToConvertAndSharesMemo(): void
    {
        $fxRate = $this->makeFxRate('2026-08-14', FxRate::INDICATOR_UF, '39000.000000');

        $repository = $this->createMock(FxRateRepository::class);
        $repository->expects(self::once())
            ->method('findLatestOnOrBefore')
            ->willReturn($fxRate);

        $sut = new ClpConverter($repository);
        $date = new \DateTimeImmutable('2026-08-14');

        $viaConvert = $sut->convert('2.0000', 'CLF', $date);
        $viaToClp = $sut->toClp('2.0000', 'CLF', $date);

        self::assertNotNull($viaConvert);
        self::assertSame($viaConvert->clpAmount, $viaToClp);
        self::assertSame('78000.0000', $viaToClp);
    }
}
