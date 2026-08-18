<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use App\Entity\Expense;
use App\Pdf\HtmlToPdfConverter;
use Twig\Environment;

final class ExpensePdfRenderer implements ExpensePdfRendererInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly HtmlToPdfConverter $converter,
        private readonly string $projectDirectory,
    ) {
    }

    public function render(Expense $expense): string
    {
        if ($expense->getId() === null) {
            throw new \DomainException('A saved expense is required to render its PDF.');
        }

        $html = $this->twig->render('expense/pdf.html.twig', [
            'expense' => $expense,
            'defaultLogo' => $this->defaultLogoDataUri(),
        ]);

        try {
            return $this->converter->convertToPdf($html, [
                'format' => 'A4',
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_left' => 20,
                'margin_right' => 20,
                'filename' => 'expense-' . $expense->getId(),
            ]);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Expense PDF rendering failed: ' . $exception->getMessage(), 0, $exception);
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
