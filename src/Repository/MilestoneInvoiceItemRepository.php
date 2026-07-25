<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\ExportableItem;
use App\FxRate\ClpConverter;
use App\Invoice\InvoiceItemRepositoryInterface;
use App\Invoice\MilestoneInvoiceItem;
use App\Repository\Query\InvoiceQuery;
use App\Repository\Query\MilestoneInvoiceQuery;

/**
 * Auto-tagged InvoiceItemRepositoryInterface implementation that MUST NOT
 * participate in the ordinary timesheet invoice fan-out
 * (InvoiceService::getInvoiceItems() calls every registered repository with
 * a plain InvoiceQuery). It only ever returns items for a
 * MilestoneInvoiceQuery, which is never used by that flow (design D2).
 */
class MilestoneInvoiceItemRepository implements InvoiceItemRepositoryInterface
{
    public function __construct(private readonly ClpConverter $converter)
    {
    }

    /**
     * @return ExportableItem[]
     */
    public function getInvoiceItemsForQuery(InvoiceQuery $query): array
    {
        if (!$query instanceof MilestoneInvoiceQuery) {
            return [];
        }

        $items = [];

        foreach ($query->getMilestones() as $milestone) {
            $value = $milestone->getValue();
            $currency = $milestone->getCurrency();

            if (null === $value || null === $currency) {
                continue;
            }

            $date = $milestone->getDueDate() ?? $milestone->getCreatedAt();
            $conversion = $this->converter->convert($value, $currency, $date);

            if (null === $conversion) {
                continue;
            }

            $items[] = new MilestoneInvoiceItem($milestone, $conversion);
        }

        return $items;
    }

    /**
     * No-op on purpose: `setExported()` receives no Invoice (see
     * InvoiceItemRepositoryInterface), so it cannot write the FK link.
     * Linking happens exclusively via MilestoneInvoiceLinkSubscriber
     * reacting to InvoiceCreatedEvent (design D3, Phase 3).
     *
     * @param ExportableItem[] $invoiceItems
     */
    public function setExported(array $invoiceItems): void
    {
    }
}
