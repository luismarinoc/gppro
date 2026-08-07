<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice;

use App\Entity\Quotation;
use App\Invoice\QuotationPricing;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuotationPricing::class)]
class QuotationPricingTest extends TestCase
{
    public function testPercentagesAreAppliedToTheRequiredBases(): void
    {
        $quotation = (new Quotation())->setDiscount('10')->setSurcharge('5')->setTax('20');
        $pricing = new QuotationPricing($quotation);

        self::assertSame(100.0, $pricing->discountAmount(1000));
        self::assertSame(50.0, $pricing->surchargeAmount(1000));
        self::assertSame(950.0, $pricing->adjustedSubtotal(1000));
        self::assertSame(190.0, $pricing->customTaxAmount(950));
    }

    public function testNullDisablesAnAdjustmentButZeroRemainsEnabled(): void
    {
        $disabled = new Quotation();
        $enabled = (new Quotation())->setDiscount('0')->setSurcharge('0')->setTax('0');

        self::assertNull((new QuotationPricing($disabled))->customTaxAmount(100));
        self::assertSame(0.0, (new QuotationPricing($enabled))->customTaxAmount(100));
        self::assertSame(100.0, (new QuotationPricing($enabled))->adjustedSubtotal(100));
    }
}
