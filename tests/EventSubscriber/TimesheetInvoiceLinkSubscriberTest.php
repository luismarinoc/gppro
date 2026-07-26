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
use App\Entity\Timesheet;
use App\Event\InvoiceCreatedEvent;
use App\EventSubscriber\TimesheetInvoiceLinkSubscriber;
use App\FxRate\ClpConversion;
use App\Invoice\InvoiceModel;
use App\Invoice\MilestoneInvoiceItem;
use App\Repository\TimesheetRepository;
use App\Tests\Invoice\DebugFormatter;
use App\Tests\Mocks\InvoiceModelFactoryFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(TimesheetInvoiceLinkSubscriber::class)]
class TimesheetInvoiceLinkSubscriberTest extends TestCase
{
    public function testSubscribesToInvoiceCreatedEvent(): void
    {
        $events = TimesheetInvoiceLinkSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(InvoiceCreatedEvent::class, $events);
        $methodName = $events[InvoiceCreatedEvent::class];
        self::assertIsString($methodName);
        self::assertTrue(method_exists(TimesheetInvoiceLinkSubscriber::class, $methodName));
    }

    public function testMarksAllSelectedTimesheetsAsInvoicedWhenAllAreLinked(): void
    {
        $timesheetA = $this->createTimesheet(10);
        $timesheetB = $this->createTimesheet(20);
        $invoice = $this->createInvoice(99);

        $model = $this->buildModelWithEntries([$timesheetA, $timesheetB]);

        $repository = $this->createMock(TimesheetRepository::class);
        $repository->expects(self::once())
            ->method('markAsInvoiced')
            ->with($invoice, [10, 20])
            ->willReturn(2);
        $repository->expects(self::never())->method('findBy');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $sut = new TimesheetInvoiceLinkSubscriber($repository, $logger);
        $sut->onInvoiceCreated(new InvoiceCreatedEvent($invoice, $model));
    }

    public function testDoesNothingForAMilestoneOnlyInvoiceAndNeverInterferesWithTheExistingFlow(): void
    {
        $invoice = $this->createInvoice(1);

        $milestone = new Milestone();
        $milestone->setName('Design phase');
        $milestone->setValue('1500.0000');
        $milestone->setCurrency('USD');

        $model = $this->buildModelWithEntries([new MilestoneInvoiceItem($milestone, ClpConversion::identity('1500.0000'))]);

        $repository = $this->createMock(TimesheetRepository::class);
        $repository->expects(self::never())->method('markAsInvoiced');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $sut = new TimesheetInvoiceLinkSubscriber($repository, $logger);
        $sut->onInvoiceCreated(new InvoiceCreatedEvent($invoice, $model));
    }

    public function testWarnsNamingTheTimesheetIdsAlreadyClaimedByAnotherInvoiceWhenAffectedIsLessThanSelected(): void
    {
        $timesheetA = $this->createTimesheet(10);
        $timesheetB = $this->createTimesheet(20);
        $invoice = $this->createInvoice(99);
        $otherInvoice = $this->createInvoice(50);

        $model = $this->buildModelWithEntries([$timesheetA, $timesheetB]);

        // timesheet 20 was concurrently claimed by another invoice before
        // our UPDATE ran, so only 1 of the 2 selected timesheets was linked
        $reloadedA = $this->createTimesheet(10);
        $reloadedA->setInvoice($invoice);
        $reloadedB = $this->createTimesheet(20);
        $reloadedB->setInvoice($otherInvoice);

        $repository = $this->createMock(TimesheetRepository::class);
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

        $sut = new TimesheetInvoiceLinkSubscriber($repository, $logger);
        $sut->onInvoiceCreated(new InvoiceCreatedEvent($invoice, $model));
    }

    private function createTimesheet(int $id): Timesheet
    {
        $timesheet = new Timesheet();
        $timesheet->setBegin(new \DateTime('2026-07-01'));
        $timesheet->setDuration(3600);
        $timesheet->setRate(100.0);

        $idProperty = new \ReflectionProperty(Timesheet::class, 'id');
        $idProperty->setValue($timesheet, $id);

        return $timesheet;
    }

    private function createInvoice(int $id): Invoice
    {
        $invoice = new Invoice();

        $idProperty = new \ReflectionProperty(Invoice::class, 'id');
        $idProperty->setValue($invoice, $id);

        return $invoice;
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
