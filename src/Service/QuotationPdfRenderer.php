<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Quotation;
use App\Invoice\QuotationInvoiceItem;
use App\Invoice\QuotationPricing;
use App\Pdf\HtmlToPdfConverter;
use Twig\Environment;

final class QuotationPdfRenderer implements QuotationPdfRendererInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly HtmlToPdfConverter $converter,
    ) {
    }

    public function render(Quotation $quotation): string
    {
        $customer = $quotation->getCustomer();
        if ($customer === null || $quotation->getId() === null) {
            throw new \DomainException('A saved quotation with a customer is required to render its PDF.');
        }

        $items = array_map(static fn ($line): QuotationInvoiceItem => new QuotationInvoiceItem($line), $quotation->getLines()->toArray());
        $subtotal = round(array_sum(array_map(static fn (QuotationInvoiceItem $item): float => $item->getRate(), $items)), 2, PHP_ROUND_HALF_UP);
        $pricing = new QuotationPricing($quotation);
        $discount = $pricing->discountAmount($subtotal);
        $surcharge = $pricing->surchargeAmount($subtotal);
        $adjustedSubtotal = $pricing->adjustedSubtotal($subtotal);
        $tax = $pricing->customTaxAmount($adjustedSubtotal);

        $currency = $customer->getCurrency() ?? Customer::DEFAULT_CURRENCY;
        $html = $this->twig->render('quotation/pdf.html.twig', [
            'quotation' => $quotation,
            'items' => $items,
            'currency' => $currency,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'surcharge_amount' => $surcharge,
            'adjusted_subtotal' => $adjustedSubtotal,
            'tax_amount' => $tax,
            'total' => round($adjustedSubtotal + ($tax ?? 0), 2, PHP_ROUND_HALF_UP),
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
