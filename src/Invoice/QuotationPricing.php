<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\Entity\Quotation;

final class QuotationPricing
{
    public function __construct(private readonly Quotation $quotation)
    {
    }

    public function discountAmount(float $subtotal): float
    {
        return $this->percentageAmount($subtotal, $this->quotation->getDiscount());
    }

    public function surchargeAmount(float $subtotal): float
    {
        return $this->percentageAmount($subtotal, $this->quotation->getSurcharge());
    }

    public function adjustedSubtotal(float $subtotal): float
    {
        return round($subtotal - $this->discountAmount($subtotal) + $this->surchargeAmount($subtotal), 2, PHP_ROUND_HALF_UP);
    }

    public function customTaxAmount(float $adjustedSubtotal): ?float
    {
        $tax = $this->quotation->getTax();
        if ($tax === null) {
            return null;
        }

        return $this->percentageAmount($adjustedSubtotal, $tax);
    }

    private function percentageAmount(float $base, ?string $percentage): float
    {
        if ($percentage === null) {
            return 0.0;
        }

        return round($base * ((float) $percentage / 100), 2, PHP_ROUND_HALF_UP);
    }
}
