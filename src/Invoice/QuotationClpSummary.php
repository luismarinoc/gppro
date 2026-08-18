<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\FxRate\ClpConversion;
use App\FxRate\ClpConverter;

/**
 * Projects a QuotationSummary entirely into CLP, deriving every line and
 * total from the single rate resolved for the quotation total.
 */
final readonly class QuotationClpSummary
{
    /** @param list<array{description: string, amount: float, unitPriceClp: string, lineTotalClp: string}> $items */
    private function __construct(
        public array $items,
        public string $subtotalClp,
        public string $discountAmountClp,
        public string $surchargeAmountClp,
        public string $adjustedSubtotalClp,
        public ?string $taxAmountClp,
        public string $totalClp,
        public ClpConversion $totalConversion,
    ) {
    }

    public static function fromSummary(QuotationSummary $summary, ClpConverter $converter, string $currency, ?\DateTimeInterface $date): ?self
    {
        $totalConversion = $converter->convert(number_format($summary->total, 4, '.', ''), $currency, $date);

        if (null === $totalConversion) {
            return null;
        }

        $toClp = static fn (float $amount): string => 'CLP' === $currency
            ? number_format($amount, 4, '.', '')
            : number_format($amount * (float) $totalConversion->rate, 4, '.', '');

        $items = array_values(array_map(static fn (QuotationInvoiceItem $item): array => [
            'description' => $item->getDescription(),
            'amount' => $item->getAmount(),
            'unitPriceClp' => $toClp($item->getFixedRate() ?? 0.0),
            'lineTotalClp' => $toClp($item->getRate()),
        ], $summary->items));

        return new self(
            $items,
            $toClp($summary->subtotal),
            $toClp($summary->discountAmount),
            $toClp($summary->surchargeAmount),
            $toClp($summary->adjustedSubtotal),
            null === $summary->taxAmount ? null : $toClp($summary->taxAmount),
            $totalConversion->clpAmount,
            $totalConversion,
        );
    }
}
