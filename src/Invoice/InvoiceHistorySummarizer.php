<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Invoice;

use App\Entity\Invoice;
use App\Entity\Project;
use App\Milestone\MilestoneTotalCalculator;
use App\Repository\MilestoneRepository;
use App\Repository\TimesheetRepository;

/**
 * Folds an already-loaded list of invoices into per (customer, project, type)
 * totals for the invoice archive listing.
 *
 * Milestone amounts (always CLP-converted, see MilestoneTotalCalculator) and
 * timesheet amounts (raw per-invoice-currency rate sum, never FX-converted)
 * are semantically different kinds of money and are therefore never merged
 * into the same row.
 *
 * A single timesheet invoice can legitimately contain timesheets from
 * several projects of the same customer; each project's share is prorated
 * from TimesheetRepository::getProjectSubtotalsByInvoiceIds() and
 * contributes to that project's own row.
 */
final class InvoiceHistorySummarizer
{
    private const NO_PROJECT_LABEL = '-';

    public function __construct(
        private readonly MilestoneRepository $milestoneRepository,
        private readonly TimesheetRepository $timesheetRepository,
        private readonly MilestoneTotalCalculator $milestoneTotalCalculator,
    ) {
    }

    /**
     * @param iterable<Invoice> $invoices
     * @return InvoiceHistorySummaryRow[]
     */
    public function summarize(iterable $invoices): array
    {
        $invoiceIds = [];
        $customerNameByInvoiceId = [];
        $currencyByInvoiceId = [];

        foreach ($invoices as $invoice) {
            $id = $invoice->getId();
            if (null === $id) {
                continue;
            }

            $invoiceIds[] = $id;
            $customerNameByInvoiceId[$id] = $this->resolveCustomerName($invoice);
            $currencyByInvoiceId[$id] = $invoice->getCurrency() ?? '';
        }

        if ([] === $invoiceIds) {
            return [];
        }

        $rows = array_merge(
            $this->summarizeMilestones($invoiceIds, $customerNameByInvoiceId),
            $this->summarizeTimesheets($invoiceIds, $customerNameByInvoiceId, $currencyByInvoiceId),
        );

        usort($rows, static function (InvoiceHistorySummaryRow $a, InvoiceHistorySummaryRow $b): int {
            return [$a->customerName, $a->projectName, $a->type] <=> [$b->customerName, $b->projectName, $b->type];
        });

        return $rows;
    }

    private function resolveCustomerName(Invoice $invoice): string
    {
        $customer = $invoice->getCustomer();

        return $customer?->getName() ?? $customer?->getCompany() ?? self::NO_PROJECT_LABEL;
    }

    private function resolveProjectName(?Project $project): string
    {
        $name = $project?->getName();

        return null !== $name && '' !== $name ? $name : self::NO_PROJECT_LABEL;
    }

    /**
     * @param int[] $invoiceIds
     * @param array<int, string> $customerNameByInvoiceId
     * @return InvoiceHistorySummaryRow[]
     */
    private function summarizeMilestones(array $invoiceIds, array $customerNameByInvoiceId): array
    {
        $milestones = $this->milestoneRepository->findByInvoiceIds($invoiceIds);

        /** @var array<string, \App\Entity\Milestone[]> $groupedMilestones */
        $groupedMilestones = [];
        /** @var array<string, array{customerName: string, projectName: string, invoiceIds: array<int, true>}> $groupMeta */
        $groupMeta = [];

        foreach ($milestones as $milestone) {
            $invoice = $milestone->getInvoice();
            $invoiceId = $invoice?->getId();

            if (null === $invoiceId || !isset($customerNameByInvoiceId[$invoiceId])) {
                continue;
            }

            $customerName = $customerNameByInvoiceId[$invoiceId];
            $projectName = $this->resolveProjectName($milestone->getProject());
            $key = $customerName . "\0" . $projectName;

            $groupedMilestones[$key][] = $milestone;
            $groupMeta[$key]['customerName'] = $customerName;
            $groupMeta[$key]['projectName'] = $projectName;
            $groupMeta[$key]['invoiceIds'][$invoiceId] = true;
        }

        $rows = [];
        foreach ($groupedMilestones as $key => $groupMilestones) {
            $total = $this->milestoneTotalCalculator->calculate($groupMilestones);
            $meta = $groupMeta[$key];

            $rows[] = InvoiceHistorySummaryRow::milestone(
                $meta['customerName'],
                $meta['projectName'],
                \count($meta['invoiceIds']),
                $total->total,
                $total->isPartial(),
                $total->excludedCount,
            );
        }

        return $rows;
    }

    /**
     * @param int[] $invoiceIds
     * @param array<int, string> $customerNameByInvoiceId
     * @param array<int, string> $currencyByInvoiceId
     * @return InvoiceHistorySummaryRow[]
     */
    private function summarizeTimesheets(array $invoiceIds, array $customerNameByInvoiceId, array $currencyByInvoiceId): array
    {
        $subtotals = $this->timesheetRepository->getProjectSubtotalsByInvoiceIds($invoiceIds);

        /** @var array<string, array{customerName: string, projectName: string, currency: string, amount: float, duration: int, invoiceIds: array<int, true>}> $groups */
        $groups = [];

        foreach ($subtotals as $subtotal) {
            $invoiceId = $subtotal['invoiceId'];

            if (!isset($customerNameByInvoiceId[$invoiceId])) {
                continue;
            }

            $customerName = $customerNameByInvoiceId[$invoiceId];
            $projectName = '' !== $subtotal['projectName'] ? $subtotal['projectName'] : self::NO_PROJECT_LABEL;
            $currency = $currencyByInvoiceId[$invoiceId] ?? '';
            $key = $customerName . "\0" . $projectName . "\0" . $currency;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'customerName' => $customerName,
                    'projectName' => $projectName,
                    'currency' => $currency,
                    'amount' => 0.0,
                    'duration' => 0,
                    'invoiceIds' => [],
                ];
            }

            $groups[$key]['amount'] += $subtotal['amount'];
            $groups[$key]['duration'] += $subtotal['duration'];
            $groups[$key]['invoiceIds'][$invoiceId] = true;
        }

        $rows = [];
        foreach ($groups as $group) {
            $rows[] = InvoiceHistorySummaryRow::timesheet(
                $group['customerName'],
                $group['projectName'],
                $group['currency'],
                \count($group['invoiceIds']),
                number_format($group['amount'], 2, '.', ''),
                $group['duration'],
            );
        }

        return $rows;
    }
}
