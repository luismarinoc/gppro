<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice;

use App\Entity\Quotation;
use App\Entity\QuotationLine;
use App\FxRate\ClpConversion;
use App\FxRate\ClpConverter;
use App\Invoice\QuotationClpSummary;
use App\Invoice\QuotationSummary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuotationClpSummary::class)]
final class QuotationClpSummaryTest extends TestCase
{
    private function buildSummary(): QuotationSummary
    {
        $quotation = (new Quotation())->setDiscount('10')->setSurcharge('5')->setTax('20');
        $quotation->addLine((new QuotationLine())->setDescription('Service')->setQuantity('2')->setUnitPrice('100'));

        // subtotal 200, discount 20, surcharge 10, adjustedSubtotal 190, tax 38, total 228
        return QuotationSummary::fromQuotation($quotation);
    }

    public function testClpCurrencyPassesThroughWithoutCallingConvertWithARate(): void
    {
        $summary = $this->buildSummary();

        $converter = $this->createMock(ClpConverter::class);
        $converter->expects(self::once())
            ->method('convert')
            ->with('228.0000', 'CLP', null)
            ->willReturn(ClpConversion::identity('228.0000'));

        $result = QuotationClpSummary::fromSummary($summary, $converter, 'CLP', null);

        self::assertNotNull($result);
        self::assertSame('200.0000', $result->subtotalClp);
        self::assertSame('20.0000', $result->discountAmountClp);
        self::assertSame('10.0000', $result->surchargeAmountClp);
        self::assertSame('190.0000', $result->adjustedSubtotalClp);
        self::assertSame('38.0000', $result->taxAmountClp);
        self::assertSame('228.0000', $result->totalClp);

        self::assertCount(1, $result->items);
        self::assertSame('Service', $result->items[0]['description']);
        self::assertSame(2.0, $result->items[0]['amount']);
        self::assertSame('100.0000', $result->items[0]['unitPriceClp']);
        self::assertSame('200.0000', $result->items[0]['lineTotalClp']);
    }

    public function testForeignCurrencyCallsConvertExactlyOnceAndDerivesEveryFieldFromTheSameRate(): void
    {
        $summary = $this->buildSummary();
        $date = new \DateTimeImmutable('2026-08-01');

        $conversion = ClpConversion::converted('228.0000', 'USD', '950.000000', new \DateTimeImmutable('2026-08-01'), '216600.0000');
        $converter = $this->createMock(ClpConverter::class);
        $converter->expects(self::once())
            ->method('convert')
            ->with('228.0000', 'USD', $date)
            ->willReturn($conversion);

        $result = QuotationClpSummary::fromSummary($summary, $converter, 'USD', $date);

        self::assertNotNull($result);
        // totalClp must come straight from the conversion record, not be recomputed
        self::assertSame('216600.0000', $result->totalClp);
        self::assertSame($conversion, $result->totalConversion);

        // every other field derives from the same 950 rate
        self::assertSame('190000.0000', $result->subtotalClp);
        self::assertSame('19000.0000', $result->discountAmountClp);
        self::assertSame('9500.0000', $result->surchargeAmountClp);
        self::assertSame('180500.0000', $result->adjustedSubtotalClp);
        self::assertSame('36100.0000', $result->taxAmountClp);

        self::assertSame('95000.0000', $result->items[0]['unitPriceClp']);
        self::assertSame('190000.0000', $result->items[0]['lineTotalClp']);
    }

    public function testReturnsNullWhenNoRateIsAvailable(): void
    {
        $summary = $this->buildSummary();

        $converter = $this->createMock(ClpConverter::class);
        $converter->method('convert')->willReturn(null);

        self::assertNull(QuotationClpSummary::fromSummary($summary, $converter, 'USD', null));
    }

    public function testTaxAmountClpIsNullWhenSummaryHasNoTax(): void
    {
        $quotation = new Quotation();
        $quotation->addLine((new QuotationLine())->setDescription('Service')->setQuantity('1')->setUnitPrice('100'));
        $summary = QuotationSummary::fromQuotation($quotation);

        $converter = $this->createMock(ClpConverter::class);
        $converter->method('convert')->willReturn(ClpConversion::identity('100.0000'));

        $result = QuotationClpSummary::fromSummary($summary, $converter, 'CLP', null);

        self::assertNotNull($result);
        self::assertNull($result->taxAmountClp);
    }
}
