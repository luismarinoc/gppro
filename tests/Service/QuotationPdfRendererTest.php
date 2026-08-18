<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Service;

use App\Entity\Customer;
use App\Entity\Quotation;
use App\Entity\QuotationLine;
use App\FxRate\ClpConversion;
use App\FxRate\ClpConverter;
use App\Invoice\QuotationClpSummary;
use App\Pdf\HtmlToPdfConverter;
use App\Service\QuotationPdfRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

#[CoversClass(QuotationPdfRenderer::class)]
#[CoversClass(QuotationClpSummary::class)]
class QuotationPdfRendererTest extends TestCase
{
    public function testRendersQuotationDataWithoutCreatingARepositoryFile(): void
    {
        $customer = new Customer('PDF customer');
        $customer->setCurrency('EUR');
        $quotation = (new Quotation())
            ->setCustomer($customer)
            ->setCurrency(Quotation::CURRENCY_USD)
            ->setTax('20')
            ->setDiscount('10')
            ->setSurcharge('5');
        $quotation->addLine((new QuotationLine())->setDescription('Service')->setQuantity('2')->setUnitPrice('100'));
        $id = new \ReflectionProperty(Quotation::class, 'id');
        $id->setValue($quotation, 7);

        $conversion = ClpConversion::converted('228.0000', Quotation::CURRENCY_USD, '950.000000', new \DateTimeImmutable('2026-08-01'), '216600.0000');
        $clpConverter = $this->createMock(ClpConverter::class);
        $clpConverter->expects(self::once())->method('convert')->with('228.0000', Quotation::CURRENCY_USD, null)->willReturn($conversion);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->with('quotation/pdf.html.twig', self::callback(static function (array $context) use ($conversion): bool {
            if (!isset($context['clpSummary']) || !$context['clpSummary'] instanceof QuotationClpSummary) {
                return false;
            }
            $clpSummary = $context['clpSummary'];

            // derived from the mocked 950 rate, not the (differing) customer currency
            return $clpSummary->subtotalClp === '190000.0000'
                && $clpSummary->discountAmountClp === '19000.0000'
                && $clpSummary->surchargeAmountClp === '9500.0000'
                && $clpSummary->taxAmountClp === '36100.0000'
                && $clpSummary->totalClp === $conversion->clpAmount
                && $clpSummary->totalConversion === $conversion;
        }))->willReturn('<html></html>');
        $converter = $this->createMock(HtmlToPdfConverter::class);
        $converter->expects(self::once())->method('convertToPdf')->with('<html></html>', self::arrayHasKey('filename'))->willReturn('%PDF');

        self::assertSame('%PDF', (new QuotationPdfRenderer($twig, $converter, $clpConverter, \dirname(__DIR__, 2)))->render($quotation));
    }

    public function testThrowsWhenNoClpExchangeRateIsAvailable(): void
    {
        $customer = new Customer('PDF customer');
        $quotation = (new Quotation())->setCustomer($customer)->setCurrency(Quotation::CURRENCY_USD);
        $quotation->addLine((new QuotationLine())->setDescription('Service')->setQuantity('1')->setUnitPrice('100'));
        $id = new \ReflectionProperty(Quotation::class, 'id');
        $id->setValue($quotation, 8);

        $clpConverter = $this->createMock(ClpConverter::class);
        $clpConverter->method('convert')->willReturn(null);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');
        $converter = $this->createMock(HtmlToPdfConverter::class);
        $converter->expects(self::never())->method('convertToPdf');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('No exchange rate is available to convert this quotation to CLP.');

        (new QuotationPdfRenderer($twig, $converter, $clpConverter, \dirname(__DIR__, 2)))->render($quotation);
    }
}
