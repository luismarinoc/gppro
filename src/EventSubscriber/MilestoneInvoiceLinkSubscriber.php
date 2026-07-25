<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber;

use App\Event\InvoiceCreatedEvent;
use App\Invoice\MilestoneInvoiceItem;
use App\Repository\MilestoneRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes the milestone -> invoice FK link (design D3). This is the ONLY
 * place that calls MilestoneRepository::markAsInvoiced() — never the
 * controller, never MilestoneInvoiceItemRepository::setExported() (which is
 * a documented no-op, see App\Repository\MilestoneInvoiceItemRepository).
 *
 * InvoiceService::createInvoice() dispatches InvoiceCreatedEvent AFTER the
 * invoice row is saved (so it has an ID) and after
 * markEntriesAsExported($model->getEntries()) already ran. The model's raw
 * entries (not the calculator-processed InvoiceItem rows) are inspected
 * here, filtered for MilestoneInvoiceItem, and their underlying Milestone
 * IDs are bulk-linked.
 */
final class MilestoneInvoiceLinkSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MilestoneRepository $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceCreatedEvent::class => 'onInvoiceCreated',
        ];
    }

    public function onInvoiceCreated(InvoiceCreatedEvent $event): void
    {
        $milestoneIds = [];
        foreach ($event->getInvoiceModel()->getEntries() as $entry) {
            if (!$entry instanceof MilestoneInvoiceItem) {
                continue;
            }

            $id = $entry->getMilestone()->getId();
            if (null !== $id) {
                $milestoneIds[] = $id;
            }
        }

        if ([] === $milestoneIds) {
            return;
        }

        $invoice = $event->getInvoice();
        $affected = $this->repository->markAsInvoiced($invoice, $milestoneIds);

        if ($affected >= \count($milestoneIds)) {
            return;
        }

        // Concurrency guard (design D3): markAsInvoiced() only links rows
        // still matching `invoice IS NULL`. Reload the selection to name
        // exactly which milestones lost the race to another invoice.
        $alreadyClaimed = [];
        foreach ($this->repository->findBy(['id' => $milestoneIds]) as $milestone) {
            $linkedInvoice = $milestone->getInvoice();
            if (null === $linkedInvoice || $linkedInvoice->getId() !== $invoice->getId()) {
                $alreadyClaimed[] = $milestone->getId();
            }
        }

        $this->logger->warning(\sprintf(
            'Milestone invoice link: %d of %d selected milestones were already claimed by another invoice (IDs: %s).',
            \count($alreadyClaimed),
            \count($milestoneIds),
            implode(', ', $alreadyClaimed)
        ));
    }
}
