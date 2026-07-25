<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\FxRate;

use App\Entity\FxRate;
use App\FxRate\FxRateSynchronizer;
use App\FxRate\FxRateSyncResult;
use App\FxRate\FxRateSyncStatus;
use App\FxRate\MindicadorClient;
use App\FxRate\MindicadorUnavailableException;
use App\Repository\FxRateRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FxRateSynchronizer::class)]
#[CoversClass(FxRateSyncResult::class)]
class FxRateSynchronizerTest extends TestCase
{
    public function testSkipsIndicatorWithNoDataAndPersistsNothing(): void
    {
        $date = new \DateTimeImmutable('2026-07-19');

        $client = $this->createMock(MindicadorClient::class);
        $client->method('fetchValue')->willReturn(null);

        $repository = $this->createMock(FxRateRepository::class);
        $repository->expects(self::never())->method('upsert');

        $sut = new FxRateSynchronizer($client, $repository);
        $results = $sut->sync($date, false);

        self::assertSame(FxRateSyncStatus::SKIPPED_NO_DATA, $results[FxRate::INDICATOR_USD]->status);
        self::assertSame(FxRateSyncStatus::SKIPPED_NO_DATA, $results[FxRate::INDICATOR_UF]->status);
    }

    public function testPersistsIndicatorWithValue(): void
    {
        $date = new \DateTimeImmutable('2026-07-20');

        $client = $this->createMock(MindicadorClient::class);
        $client->method('fetchValue')->willReturn('950.5');

        $repository = $this->createMock(FxRateRepository::class);
        $repository->expects(self::exactly(2))
            ->method('upsert')
            ->willReturn(true);

        $sut = new FxRateSynchronizer($client, $repository);
        $results = $sut->sync($date, false);

        self::assertSame(FxRateSyncStatus::PERSISTED, $results[FxRate::INDICATOR_USD]->status);
        self::assertSame('950.5', $results[FxRate::INDICATOR_USD]->value);
        self::assertSame(FxRateSyncStatus::PERSISTED, $results[FxRate::INDICATOR_UF]->status);
    }

    public function testDoesNotOverwriteExistingRowWithoutForce(): void
    {
        $date = new \DateTimeImmutable('2026-07-10');

        $client = $this->createMock(MindicadorClient::class);
        $client->method('fetchValue')->willReturn('39012.34');

        $repository = $this->createMock(FxRateRepository::class);
        $repository->method('upsert')->willReturn(false);

        $sut = new FxRateSynchronizer($client, $repository);
        $results = $sut->sync($date, false);

        self::assertSame(FxRateSyncStatus::SKIPPED_EXISTING, $results[FxRate::INDICATOR_USD]->status);
    }

    public function testOverwritesExistingRowWithForce(): void
    {
        $date = new \DateTimeImmutable('2026-07-10');

        $client = $this->createMock(MindicadorClient::class);
        $client->method('fetchValue')->willReturn('39100.00');

        $repository = $this->createMock(FxRateRepository::class);
        $repository->expects(self::exactly(2))
            ->method('upsert')
            ->with(self::anything(), self::anything(), self::anything(), true)
            ->willReturn(true);

        $sut = new FxRateSynchronizer($client, $repository);
        $results = $sut->sync($date, true);

        self::assertSame(FxRateSyncStatus::PERSISTED, $results[FxRate::INDICATOR_UF]->status);
    }

    public function testUsdSucceedsWhileUfFailsIndependently(): void
    {
        $date = new \DateTimeImmutable('2026-07-15');

        $client = $this->createMock(MindicadorClient::class);
        $client->method('fetchValue')->willReturnCallback(
            static function (string $indicator) {
                if (FxRate::INDICATOR_USD === $indicator) {
                    return '900.10';
                }

                throw new MindicadorUnavailableException('malformed JSON');
            }
        );

        $repository = $this->createMock(FxRateRepository::class);
        $repository->expects(self::once())
            ->method('upsert')
            ->with($date, FxRate::INDICATOR_USD, '900.10', false)
            ->willReturn(true);

        $sut = new FxRateSynchronizer($client, $repository);
        $results = $sut->sync($date, false);

        self::assertSame(FxRateSyncStatus::PERSISTED, $results[FxRate::INDICATOR_USD]->status);
        self::assertSame(FxRateSyncStatus::FAILED, $results[FxRate::INDICATOR_UF]->status);
        self::assertSame('malformed JSON', $results[FxRate::INDICATOR_UF]->errorMessage);
    }
}
