<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber;

use App\Entity\Timesheet;
use App\Event\InvoiceCreatedEvent;
use App\Repository\TimesheetRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes the timesheet -> invoice FK link, the same way
 * MilestoneInvoiceLinkSubscriber does for milestones. `Timesheet::exported`
 * is NOT a reliable "was this invoiced" signal — it's shared with the
 * unrelated CSV/Excel export feature (ExportController), which flips the
 * same boolean for timesheets that were never invoiced at all.
 */
final class TimesheetInvoiceLinkSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TimesheetRepository $repository,
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
        $timesheetIds = [];
        foreach ($event->getInvoiceModel()->getEntries() as $entry) {
            if (!$entry instanceof Timesheet) {
                continue;
            }

            $id = $entry->getId();
            if (null !== $id) {
                $timesheetIds[] = $id;
            }
        }

        if ([] === $timesheetIds) {
            return;
        }

        $invoice = $event->getInvoice();
        $affected = $this->repository->markAsInvoiced($invoice, $timesheetIds);

        if ($affected >= \count($timesheetIds)) {
            return;
        }

        $alreadyClaimed = [];
        foreach ($this->repository->findBy(['id' => $timesheetIds]) as $timesheet) {
            $linkedInvoice = $timesheet->getInvoice();
            if (null === $linkedInvoice || $linkedInvoice->getId() !== $invoice->getId()) {
                $alreadyClaimed[] = $timesheet->getId();
            }
        }

        $this->logger->warning(\sprintf(
            'Timesheet invoice link: %d of %d selected timesheets were already claimed by another invoice (IDs: %s).',
            \count($alreadyClaimed),
            \count($timesheetIds),
            implode(', ', $alreadyClaimed)
        ));
    }
}
