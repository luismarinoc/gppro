<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\FxRate;

use App\Entity\FxRate;
use App\Repository\FxRateRepository;

/**
 * Orchestrates fetching and persisting USD and UF for a given date.
 *
 * Each indicator is fetched and persisted independently: a failure or
 * no-data result for one MUST NOT block persisting a valid value for the
 * other on the same date (spec: "Independent persistence per indicator").
 */
final class FxRateSynchronizer
{
    public function __construct(
        private readonly MindicadorClient $client,
        private readonly FxRateRepository $repository,
    ) {
    }

    /**
     * @return array<string, FxRateSyncResult> keyed by indicator (FxRate::INDICATOR_*)
     */
    public function sync(\DateTimeImmutable $date, bool $force): array
    {
        $results = [];

        foreach (FxRate::INDICATORS as $indicator) {
            $results[$indicator] = $this->syncIndicator($indicator, $date, $force);
        }

        return $results;
    }

    private function syncIndicator(string $indicator, \DateTimeImmutable $date, bool $force): FxRateSyncResult
    {
        try {
            $value = $this->client->fetchValue($indicator, $date);
        } catch (MindicadorUnavailableException $e) {
            return FxRateSyncResult::failed($indicator, $e->getMessage());
        }

        if (null === $value) {
            return FxRateSyncResult::skippedNoData($indicator);
        }

        $persisted = $this->repository->upsert($date, $indicator, $value, $force);

        return $persisted
            ? FxRateSyncResult::persisted($indicator, $value)
            : FxRateSyncResult::skippedExisting($indicator, $value);
    }
}
