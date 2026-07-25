<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\FxRate;

/**
 * Record of a single amount->CLP conversion (or CLP passthrough).
 *
 * `rate`/`rateDate` are null for CLP: the FX table was never consulted, so
 * there is no rate to report. This is distinct from a rate of `1.000000`,
 * which would mean a lookup happened and just resolved to parity.
 */
final readonly class ClpConversion
{
    private function __construct(
        public string $sourceAmount,
        public string $sourceCurrency,
        public ?string $rate,
        public ?\DateTimeImmutable $rateDate,
        public string $clpAmount,
    ) {
    }

    /**
     * CLP -> CLP: amount passes through untouched, no rate applied.
     */
    public static function identity(string $amount): self
    {
        return new self($amount, 'CLP', null, null, $amount);
    }

    public static function converted(
        string $amount,
        string $currency,
        string $rate,
        \DateTimeImmutable $rateDate,
        string $clpAmount,
    ): self {
        return new self($amount, $currency, $rate, $rateDate, $clpAmount);
    }

    public function isConverted(): bool
    {
        return null !== $this->rate;
    }
}
