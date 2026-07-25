<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\Repository\MilestoneInvoiceItemRepository;
use App\Repository\Query\MilestoneInvoiceQuery;

/**
 * Assembles an InvoiceModel for a set of selected milestones, entirely
 * bypassing InvoiceService::getInvoiceItems() (which fans out to EVERY
 * tagged InvoiceItemRepositoryInterface with a plain InvoiceQuery and would
 * otherwise pull in unrelated billable timesheets of the same customer).
 *
 * Instead, the model is built directly via the now-public
 * InvoiceService::createModelWithoutEntries(), and only
 * MilestoneInvoiceItemRepository is asked for entries (design D2).
 */
final class MilestoneInvoiceService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly MilestoneInvoiceItemRepository $repository,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function createModel(MilestoneInvoiceQuery $query): InvoiceModel
    {
        $model = $this->invoiceService->createModelWithoutEntries($query);
        $model->addEntries($this->repository->getInvoiceItemsForQuery($query));

        return $model;
    }
}
