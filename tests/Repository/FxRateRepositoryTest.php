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
}
