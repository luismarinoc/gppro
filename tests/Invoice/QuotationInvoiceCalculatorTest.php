<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice;

use App\Entity\Quotation;
use App\Invoice\CalculatorInterface;
use App\Invoice\QuotationInvoiceCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuotationInvoiceCalculator::class)]
class QuotationInvoiceCalculatorTest extends TestCase
{
    public function testCustomTaxReplacesTemplateTaxAndUsesAdjustedSubtotal(): void
    {
        $calculator = $this->createMock(CalculatorInterface::class);
        $calculator->method('getSubtotal')->willReturn(1000.0);
        $calculator->method('getTax')->willReturn(190.0);
        $quotation = (new Quotation())->setDiscount('10')->setSurcharge('5')->setTax('20');
        $sut = new QuotationInvoiceCalculator($calculator, $quotation);

        self::assertSame(950.0, $sut->getSubtotal());
        self::assertSame(190.0, $sut->getTax());
        self::assertSame([], $sut->getTaxRows());
        self::assertSame(1140.0, $sut->getTotal());
    }

    public function testNullTaxPreservesTemplateTaxRowsAndAmount(): void
    {
        $rows = [];
        $calculator = $this->createMock(CalculatorInterface::class);
        $calculator->method('getSubtotal')->willReturn(1000.0);
        $calculator->method('getTax')->willReturn(190.0);
        $calculator->expects(self::once())->method('getTaxRows')->willReturn($rows);
        $sut = new QuotationInvoiceCalculator($calculator, new Quotation());

        self::assertSame(190.0, $sut->getTax());
        self::assertSame($rows, $sut->getTaxRows());
    }
}
