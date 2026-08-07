<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\ExportableItem;
use App\Invoice\InvoiceItemRepositoryInterface;
use App\Invoice\QuotationInvoiceItem;
use App\Repository\Query\InvoiceQuery;
use App\Repository\Query\QuotationInvoiceQuery;

final class QuotationInvoiceItemRepository implements InvoiceItemRepositoryInterface
{
    public function getInvoiceItemsForQuery(InvoiceQuery $query): array
    {
        if (!$query instanceof QuotationInvoiceQuery || $query->getQuotation() === null) {
            return [];
        }

        $items = [];
        foreach ($query->getQuotation()->getLines() as $line) {
            $items[] = new QuotationInvoiceItem($line);
        }

        return $items;
    }

    /** @param ExportableItem[] $invoiceItems */
    public function setExported(array $invoiceItems): void
    {
    }
}
