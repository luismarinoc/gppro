<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\Entity\Quotation;
use App\FxRate\ClpConverter;
use App\Invoice\QuotationSummary;
use App\Pdf\HtmlToPdfConverter;
use Twig\Environment;

final class QuotationPdfRenderer implements QuotationPdfRendererInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly HtmlToPdfConverter $converter,
        private readonly ClpConverter $clpConverter,
    ) {
    }

    public function render(Quotation $quotation): string
    {
        $customer = $quotation->getCustomer();
        if ($customer === null || $quotation->getId() === null) {
            throw new \DomainException('A saved quotation with a customer is required to render its PDF.');
        }

        $summary = QuotationSummary::fromQuotation($quotation);

        $currency = $quotation->getCurrency();
        $html = $this->twig->render('quotation/pdf.html.twig', [
            'quotation' => $quotation,
            'items' => $summary->items,
            'currency' => $currency,
            'subtotal' => $summary->subtotal,
            'discount_amount' => $summary->discountAmount,
            'surcharge_amount' => $summary->surchargeAmount,
            'adjusted_subtotal' => $summary->adjustedSubtotal,
            'tax_amount' => $summary->taxAmount,
            'total' => $summary->total,
            'clpConversion' => $this->clpConverter->convert(
                number_format($summary->total, 4, '.', ''),
                $currency,
                $quotation->getValidUntil()
            ),
        ]);

        try {
            return $this->converter->convertToPdf($html, [
                'format' => 'A4',
                'margin_top' => 12,
                'margin_bottom' => 12,
                'filename' => 'quotation-' . $quotation->getId(),
            ]);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Quotation PDF rendering failed: ' . $exception->getMessage(), 0, $exception);
        }
    }
}
