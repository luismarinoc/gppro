<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\EventSubscriber;

use App\Entity\Invoice;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Event\InvoiceCreatedEvent;
use App\EventSubscriber\MilestoneInvoiceLinkSubscriber;
use App\FxRate\ClpConversion;
use App\Invoice\InvoiceModel;
use App\Invoice\MilestoneInvoiceItem;
use App\Repository\MilestoneRepository;
use App\Tests\Invoice\DebugFormatter;
use App\Tests\Mocks\InvoiceModelFactoryFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(MilestoneInvoiceLinkSubscriber::class)]
class MilestoneInvoiceLinkSubscriberTest extends TestCase
{
    public function testSubscribesToInvoiceCreatedEvent(): void
    {
        $events = MilestoneInvoiceLinkSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(InvoiceCreatedEvent::class, $events);
        $methodName = $events[InvoiceCreatedEvent::class];
        self::assertIsString($methodName);
        self::assertTrue(method_exists(MilestoneInvoiceLinkSubscriber::class, $methodName));
    }

    public function testMarksAllSelectedMilestonesAsInvoicedWhenAllAreLinked(): void
    {
        $milestoneA = $this->createMilestone(10, 'Design phase');
        $milestoneB = $this->createMilestone(20, 'Delivery phase');
        $invoice = $this->createInvoice(99);

        $model = $this->buildModelWithEntries([
            $this->createMilestoneInvoiceItem($milestoneA),
            $this->createMilestoneInvoiceItem($milestoneB),
        ]);

        $repository = $this->createMock(MilestoneRepository::class);
        $repository->expects(self::once())
            ->method('markAsInvoiced')
            ->with($invoice, [10, 20])
            ->willReturn(2);
        $repository->expects(self::never())->method('findBy');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $sut = new MilestoneInvoiceLinkSubscriber($repository, $logger);
        $sut->onInvoiceCreated(new InvoiceCreatedEvent($invoice, $model));
    }

    public function testDoesNothingForATimesheetOnlyInvoiceAndNeverInterferesWithTheExistingFlow(): void
    {
        $invoice = $this->createInvoice(1);

        $timesheet = new Timesheet();
        $timesheet->setBegin(new \DateTime('2026-07-01'));
        $timesheet->setDuration(3600);
        $timesheet->setRate(100.0);

        $model = $this->buildModelWithEntries([$timesheet]);

        $repository = $this->createMock(MilestoneRepository::class);
        $repository->expects(self::never())->method('markAsInvoiced');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $sut = new MilestoneInvoiceLinkSubscriber($repository, $logger);
        $sut->onInvoiceCreated(new InvoiceCreatedEvent($invoice, $model));
    }

    public function testWarnsNamingTheMilestoneIdsAlreadyClaimedByAnotherInvoiceWhenAffectedIsLessThanSelected(): void
    {
        $milestoneA = $this->createMilestone(10, 'Design phase');
        $milestoneB = $this->createMilestone(20, 'Delivery phase');
        $invoice = $this->createInvoice(99);
        $otherInvoice = $this->createInvoice(50);

        $model = $this->buildModelWithEntries([
            $this->createMilestoneInvoiceItem($milestoneA),
            $this->createMilestoneInvoiceItem($milestoneB),
        ]);

        // milestone 20 was concurrently claimed by another invoice before
        // our UPDATE ran, so only 1 of the 2 selected milestones was linked
        $reloadedA = $this->createMilestone(10, 'Design phase');
        $reloadedA->setInvoice($invoice);
        $reloadedB = $this->createMilestone(20, 'Delivery phase');
        $reloadedB->setInvoice($otherInvoice);

        $repository = $this->createMock(MilestoneRepository::class);
        $repository->expects(self::once())
            ->method('markAsInvoiced')
            ->with($invoice, [10, 20])
            ->willReturn(1);
        $repository->expects(self::once())
            ->method('findBy')
            ->with(['id' => [10, 20]])
            ->willReturn([$reloadedA, $reloadedB]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('20'));

        $sut = new MilestoneInvoiceLinkSubscriber($repository, $logger);
        $sut->onInvoiceCreated(new InvoiceCreatedEvent($invoice, $model));
    }

    private function createMilestone(int $id, string $name): Milestone
    {
        $milestone = new Milestone();
        $milestone->setProject(new Project());
        $milestone->setName($name);
        $milestone->setValue('1500.0000');
        $milestone->setCurrency('USD');

        $idProperty = new \ReflectionProperty(Milestone::class, 'id');
        $idProperty->setValue($milestone, $id);

        return $milestone;
    }

    private function createInvoice(int $id): Invoice
    {
        $invoice = new Invoice();

        $idProperty = new \ReflectionProperty(Invoice::class, 'id');
        $idProperty->setValue($invoice, $id);

        return $invoice;
    }

    private function createMilestoneInvoiceItem(Milestone $milestone): MilestoneInvoiceItem
    {
        return new MilestoneInvoiceItem($milestone, ClpConversion::identity('1500.0000'));
    }

    /**
     * @param array<int, \App\Entity\ExportableItem> $entries
     */
    private function buildModelWithEntries(array $entries): InvoiceModel
    {
        $customer = new \App\Entity\Customer('foo');
        $template = new \App\Entity\InvoiceTemplate();
        $query = new \App\Repository\Query\InvoiceQuery();

        $model = (new InvoiceModelFactoryFactory($this))->create()->createModel(new DebugFormatter(), $customer, $template, $query);
        $model->addEntries($entries);

        return $model;
    }
}
