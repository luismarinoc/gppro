<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\FxRate;

/**
 * Outcome of synchronizing a single (date, indicator) pair.
 */
final class FxRateSyncResult
{
    private function __construct(
        public readonly string $indicator,
        public readonly FxRateSyncStatus $status,
        public readonly ?string $value = null,
        public readonly ?string $errorMessage = null,
    ) {
    }

    public static function persisted(string $indicator, string $value): self
    {
        return new self($indicator, FxRateSyncStatus::PERSISTED, $value);
    }

    public static function skippedExisting(string $indicator, string $value): self
    {
        return new self($indicator, FxRateSyncStatus::SKIPPED_EXISTING, $value);
    }

    public static function skippedNoData(string $indicator): self
    {
        return new self($indicator, FxRateSyncStatus::SKIPPED_NO_DATA);
    }

    public static function failed(string $indicator, string $errorMessage): self
    {
        return new self($indicator, FxRateSyncStatus::FAILED, null, $errorMessage);
    }

    public function isFailure(): bool
    {
        return FxRateSyncStatus::FAILED === $this->status;
    }
}
