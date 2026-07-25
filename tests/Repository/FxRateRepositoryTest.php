<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\FxRate;
use App\Repository\FxRateRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(FxRateRepository::class)]
#[Group('integration')]
class FxRateRepositoryTest extends AbstractRepositoryTestCase
{
    private function getRepository(): FxRateRepository
    {
        /** @var FxRateRepository $repository */
        $repository = $this->getEntityManager()->getRepository(FxRate::class);

        return $repository;
    }

    public function testUniqueConstraintRejectsDuplicateDateAndIndicator(): void
    {
        $em = $this->getEntityManager();
        $date = new \DateTimeImmutable('2026-07-20');

        $first = new FxRate();
        $first->setDate($date);
        $first->setIndicator(FxRate::INDICATOR_USD);
        $first->setRateValue('970.500000');
        $em->persist($first);
        $em->flush();

        $second = new FxRate();
        $second->setDate($date);
        $second->setIndicator(FxRate::INDICATOR_USD);
        $second->setRateValue('971.000000');
        $em->persist($second);

        $this->expectException(UniqueConstraintViolationException::class);
        $em->flush();
    }

    public function testDecimalValueRoundTripsWithoutPrecisionLoss(): void
    {
        $em = $this->getEntityManager();
        $repository = $this->getRepository();
        $date = new \DateTimeImmutable('2026-07-21');

        $fxRate = new FxRate();
        $fxRate->setDate($date);
        $fxRate->setIndicator(FxRate::INDICATOR_UF);
        $fxRate->setRateValue('39123.456789');
        $repository->saveFxRate($fxRate);

        $em->clear();

        $stored = $repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_UF);

        self::assertNotNull($stored);
        self::assertSame('39123.456789', $stored->getRateValue());
    }

    public function testUpsertSkipsExistingRowWithoutForce(): void
    {
        $repository = $this->getRepository();
        $date = new \DateTimeImmutable('2026-07-22');

        self::assertTrue($repository->upsert($date, FxRate::INDICATOR_USD, '900.000000', false));
        self::assertFalse($repository->upsert($date, FxRate::INDICATOR_USD, '999.000000', false));

        $stored = $repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_USD);
        self::assertNotNull($stored);
        self::assertSame('900.000000', $stored->getRateValue());
    }

    public function testUpsertOverwritesExistingRowWithForceAndRefreshesModifiedAt(): void
    {
        $repository = $this->getRepository();
        $date = new \DateTimeImmutable('2026-07-23');

        $repository->upsert($date, FxRate::INDICATOR_UF, '38000.000000', false);
        $result = $repository->upsert($date, FxRate::INDICATOR_UF, '38500.000000', true);

        self::assertTrue($result);

        $stored = $repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_UF);
        self::assertNotNull($stored);
        self::assertSame('38500.000000', $stored->getRateValue());
        self::assertNotNull($stored->getModifiedAt());
    }

    public function testDeleteFxRateRemovesRow(): void
    {
        $repository = $this->getRepository();
        $date = new \DateTimeImmutable('2026-07-24');

        $fxRate = new FxRate();
        $fxRate->setDate($date);
        $fxRate->setIndicator(FxRate::INDICATOR_USD);
        $fxRate->setRateValue('935.000000');
        $repository->saveFxRate($fxRate);

        self::assertNotNull($repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_USD));

        $repository->deleteFxRate($fxRate);

        self::assertNull($repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_USD));
    }

    public function testFindLatestOnOrBeforeSkipsWeekendGapToPriorFriday(): void
    {
        $repository = $this->getRepository();

        $friday = new FxRate();
        $friday->setDate(new \DateTimeImmutable('2026-08-14'));
        $friday->setIndicator(FxRate::INDICATOR_USD);
        $friday->setRateValue('960.000000');
        $repository->saveFxRate($friday);

        $monday = new FxRate();
        $monday->setDate(new \DateTimeImmutable('2026-08-17'));
        $monday->setIndicator(FxRate::INDICATOR_USD);
        $monday->setRateValue('965.000000');
        $repository->saveFxRate($monday);

        $sunday = new \DateTimeImmutable('2026-08-16');

        $result = $repository->findLatestOnOrBefore(FxRate::INDICATOR_USD, $sunday);

        self::assertNotNull($result);
        self::assertSame('960.000000', $result->getRateValue());
    }

    public function testFindLatestOnOrBeforeReturnsExactDateHit(): void
    {
        $repository = $this->getRepository();

        $fxRate = new FxRate();
        $fxRate->setDate(new \DateTimeImmutable('2026-08-18'));
        $fxRate->setIndicator(FxRate::INDICATOR_UF);
        $fxRate->setRateValue('39200.123456');
        $repository->saveFxRate($fxRate);

        $result = $repository->findLatestOnOrBefore(FxRate::INDICATOR_UF, new \DateTimeImmutable('2026-08-18'));

        self::assertNotNull($result);
        self::assertSame('39200.123456', $result->getRateValue());
    }

    public function testFindLatestOnOrBeforeReturnsNullWhenNothingAtOrBeforeDate(): void
    {
        $repository = $this->getRepository();

        $fxRate = new FxRate();
        $fxRate->setDate(new \DateTimeImmutable('2026-08-20'));
        $fxRate->setIndicator(FxRate::INDICATOR_USD);
        $fxRate->setRateValue('970.000000');
        $repository->saveFxRate($fxRate);

        $result = $repository->findLatestOnOrBefore(FxRate::INDICATOR_USD, new \DateTimeImmutable('2026-08-19'));

        self::assertNull($result);
    }

    public function testFindLatestOnOrBeforeIsolatesByIndicator(): void
    {
        $repository = $this->getRepository();

        $uf = new FxRate();
        $uf->setDate(new \DateTimeImmutable('2026-08-21'));
        $uf->setIndicator(FxRate::INDICATOR_UF);
        $uf->setRateValue('39300.000000');
        $repository->saveFxRate($uf);

        $result = $repository->findLatestOnOrBefore(FxRate::INDICATOR_USD, new \DateTimeImmutable('2026-08-21'));

        self::assertNull($result);
    }

    public function testFindLatestReturnsMaxDateRowForIndicator(): void
    {
        $repository = $this->getRepository();

        $older = new FxRate();
        $older->setDate(new \DateTimeImmutable('2026-08-22'));
        $older->setIndicator(FxRate::INDICATOR_USD);
        $older->setRateValue('971.000000');
        $repository->saveFxRate($older);

        $newer = new FxRate();
        $newer->setDate(new \DateTimeImmutable('2026-08-24'));
        $newer->setIndicator(FxRate::INDICATOR_USD);
        $newer->setRateValue('972.000000');
        $repository->saveFxRate($newer);

        $result = $repository->findLatest(FxRate::INDICATOR_USD);

        self::assertNotNull($result);
        self::assertSame('972.000000', $result->getRateValue());
    }

    public function testFindLatestReturnsNullWhenNoRowsForIndicator(): void
    {
        $repository = $this->getRepository();

        $result = $repository->findLatest(FxRate::INDICATOR_UF);

        self::assertNull($result);
    }
}
