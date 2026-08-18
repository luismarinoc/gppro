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
use App\Invoice\QuotationClpSummary;
use App\Invoice\QuotationSummary;
use App\Pdf\HtmlToPdfConverter;
use Twig\Environment;

final class QuotationPdfRenderer implements QuotationPdfRendererInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly HtmlToPdfConverter $converter,
        private readonly ClpConverter $clpConverter,
        private readonly string $projectDirectory,
    ) {
    }

    public function render(Quotation $quotation): string
    {
        $customer = $quotation->getCustomer();
        if ($customer === null || $quotation->getId() === null) {
            throw new \DomainException('A saved quotation with a customer is required to render its PDF.');
        }

        $summary = QuotationSummary::fromQuotation($quotation);
        $clpSummary = QuotationClpSummary::fromSummary($summary, $this->clpConverter, $quotation->getCurrency(), $quotation->getValidUntil());

        if (null === $clpSummary) {
            throw new \DomainException('No exchange rate is available to convert this quotation to CLP.');
        }

        $html = $this->twig->render('quotation/pdf.html.twig', [
            'quotation' => $quotation,
            'clpSummary' => $clpSummary,
            'defaultLogo' => $this->defaultLogoDataUri(),
        ]);

        try {
            return $this->converter->convertToPdf($html, [
                'format' => 'A4',
                'margin_top' => 15,
                'margin_bottom' => 12,
                'margin_left' => 20,
                'margin_right' => 20,
                'filename' => 'quotation-' . $quotation->getId(),
            ]);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Quotation PDF rendering failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Embedded as a data URI so the PDF renderer never needs a network round-trip
     * for the default GPartner Consulting letterhead logo.
     */
    private function defaultLogoDataUri(): ?string
    {
        $path = $this->projectDirectory . '/public/gpartner-consulting-logo.png';
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($contents);
    }
}
