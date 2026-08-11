<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\Entity\Quotation;

/**
 * Computed pricing breakdown for a quotation, shared by the PDF renderer
 * and the saved-quotation view so both show the same totals.
 */
final class QuotationSummary
{
    /** @param QuotationInvoiceItem[] $items */
    private function __construct(
        public readonly array $items,
        public readonly float $subtotal,
        public readonly float $discountAmount,
        public readonly float $surchargeAmount,
        public readonly float $adjustedSubtotal,
        public readonly ?float $taxAmount,
        public readonly float $total,
    ) {
    }

    public static function fromQuotation(Quotation $quotation): self
    {
        $items = array_map(static fn ($line): QuotationInvoiceItem => new QuotationInvoiceItem($line), $quotation->getLines()->toArray());
        $subtotal = round(array_sum(array_map(static fn (QuotationInvoiceItem $item): float => $item->getRate(), $items)), 2, PHP_ROUND_HALF_UP);

        $pricing = new QuotationPricing($quotation);
        $discount = $pricing->discountAmount($subtotal);
        $surcharge = $pricing->surchargeAmount($subtotal);
        $adjustedSubtotal = $pricing->adjustedSubtotal($subtotal);
        $tax = $pricing->customTaxAmount($adjustedSubtotal);

        return new self(
            $items,
            $subtotal,
            $discount,
            $surcharge,
            $adjustedSubtotal,
            $tax,
            round($adjustedSubtotal + ($tax ?? 0), 2, PHP_ROUND_HALF_UP)
        );
    }
}
