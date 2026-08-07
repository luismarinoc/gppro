<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository\Query;

use App\Entity\Quotation;

final class QuotationInvoiceQuery extends InvoiceQuery
{
    private ?Quotation $quotation = null;

    public function setQuotation(Quotation $quotation): self
    {
        $this->quotation = $quotation;

        if ($quotation->getCustomer() === null) {
            throw new \InvalidArgumentException('Quotation customer is required.');
        }

        $this->setCustomers([$quotation->getCustomer()]);
        if ($quotation->getProject() !== null) {
            $this->setProjects([$quotation->getProject()]);
        }

        return $this;
    }

    public function getQuotation(): ?Quotation
    {
        return $this->quotation;
    }
}
