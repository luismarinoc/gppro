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
 * Converts an amount in a supported currency to CLP using `gppro_fx_rates`.
 *
 * Milestone-agnostic on purpose: the currency->indicator mapping and the
 * "date <= requested date" fallback rule live here once, for every consumer.
 */
class ClpConverter
{
    private const INDICATORS = [
        'USD' => FxRate::INDICATOR_USD,
        'CLF' => FxRate::INDICATOR_UF,
    ];

    /**
     * @var array<string, ?FxRate> memo keyed by "{indicator}|{dateKey}"
     */
    private array $rates = [];

    public function __construct(private readonly FxRateRepository $repository)
    {
    }

    /**
     * Full conversion record. Returns null when the currency has no indicator
     * mapping or no rate exists at or before $date. Never throws.
     */
    public function convert(string $amount, string $currency, ?\DateTimeInterface $date): ?ClpConversion
    {
        if ('CLP' === $currency) {
            return ClpConversion::identity($amount);
        }

        $indicator = self::INDICATORS[$currency] ?? null;

        if (null === $indicator) {
            return null;
        }

        $fxRate = $this->lookup($indicator, $date);

        if (null === $fxRate) {
            return null;
        }

        $rate = $fxRate->getRateValue();
        $rateDate = $fxRate->getDate();

        if (null === $rate || null === $rateDate) {
            return null;
        }

        $clpAmount = number_format((float) $amount * (float) $rate, 4, '.', '');

        return ClpConversion::converted($amount, $currency, $rate, $rateDate, $clpAmount);
    }

    /**
     * CLP amount only (decimal string, scale 4), or null under the exact same
     * conditions as convert(). Delegation is mandatory - no second lookup path.
     */
    public function toClp(string $amount, string $currency, ?\DateTimeInterface $date): ?string
    {
        return $this->convert($amount, $currency, $date)?->clpAmount;
    }

    private function lookup(string $indicator, ?\DateTimeInterface $date): ?FxRate
    {
        $normalizedDate = null !== $date
            ? \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0)
            : null;

        $memoKey = $indicator . '|' . ($normalizedDate?->format('Y-m-d') ?? 'latest');

        if (\array_key_exists($memoKey, $this->rates)) {
            return $this->rates[$memoKey];
        }

        $fxRate = null !== $normalizedDate
            ? $this->repository->findLatestOnOrBefore($indicator, $normalizedDate)
            : $this->repository->findLatest($indicator);

        return $this->rates[$memoKey] = $fxRate;
    }
}
