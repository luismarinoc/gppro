<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\Entity\Quotation;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class QuotationInvoiceCalculator implements CalculatorInterface
{
    public function __construct(private readonly CalculatorInterface $calculator, private readonly Quotation $quotation)
    {
    }

    public function getEntries(): array { return $this->calculator->getEntries(); }

    public function setModel(InvoiceModel $model): void { $this->calculator->setModel($model); }

    public function getSubtotal(): float
    {
        return (new QuotationPricing($this->quotation))->adjustedSubtotal($this->calculator->getSubtotal());
    }

    public function getTax(): float
    {
        $customTax = (new QuotationPricing($this->quotation))->customTaxAmount($this->getSubtotal());

        return $customTax ?? $this->calculator->getTax();
    }

    public function getTotal(): float { return round($this->getSubtotal() + $this->getTax(), 2, PHP_ROUND_HALF_UP); }

    public function getVat(): float { return $this->calculator->getVat(); }

    public function getTaxRows(): array { return $this->quotation->getTax() === null ? $this->calculator->getTaxRows() : []; }

    public function getTimeWorked(): int { return $this->calculator->getTimeWorked(); }

    public function getId(): string { return 'quotation-' . $this->calculator->getId(); }
}
