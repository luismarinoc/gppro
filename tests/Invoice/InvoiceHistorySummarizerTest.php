<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Milestone;
use App\Entity\Project;
use App\FxRate\ClpConverter;
use App\Invoice\InvoiceHistorySummarizer;
use App\Invoice\InvoiceHistorySummaryRow;
use App\Milestone\MilestoneTotalCalculator;
use App\Repository\MilestoneRepository;
use App\Repository\TimesheetRepository;
use PHPUnit\Framework\TestCase;

class InvoiceHistorySummarizerTest extends TestCase
{
    private function makeCustomer(string $name): Customer
    {
        return new Customer($name);
    }

    private function makeProject(string $name, Customer $customer): Project
    {
        $project = new Project();
        $project->setName($name);
        $project->setCustomer($customer);

        $property = new \ReflectionProperty(Project::class, 'id');
        $property->setValue($project, random_int(1, 1000000));

        return $project;
    }

    private function makeInvoice(int $id, Customer $customer, string $currency = 'CLP'): Invoice
    {
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->setCurrency($currency);

        $property = new \ReflectionProperty(Invoice::class, 'id');
        $property->setValue($invoice, $id);

        return $invoice;
    }

    private function makeMilestone(Project $project, Invoice $invoice, ?string $value, ?string $currency): Milestone
    {
        $milestone = new Milestone();
        $milestone->setName('milestone');
        $milestone->setProject($project);
        $milestone->setValue($value);
        $milestone->setCurrency($currency);
        $milestone->setInvoice($invoice);

        return $milestone;
    }

    /**
     * @param Milestone[] $milestones
     * @param list<array{invoiceId: int, projectId: int, projectName: string, amount: float, duration: int}> $timesheetSubtotals
     */
    private function makeSummarizer(array $milestones, array $timesheetSubtotals, ?ClpConverter $converter = null): InvoiceHistorySummarizer
    {
        $milestoneRepository = $this->createMock(MilestoneRepository::class);
        $milestoneRepository->method('findByInvoiceIds')->willReturn($milestones);

        $timesheetRepository = $this->createMock(TimesheetRepository::class);
        $timesheetRepository->method('getProjectSubtotalsByInvoiceIds')->willReturn($timesheetSubtotals);

        if (null === $converter) {
            $converter = $this->createMock(ClpConverter::class);
            $converter->method('toClp')->willReturnCallback(
                static fn (string $amount, string $currency) => 'CLP' === $currency ? $amount : null
            );
        }

        $calculator = new MilestoneTotalCalculator($converter);

        return new InvoiceHistorySummarizer($milestoneRepository, $timesheetRepository, $calculator);
    }

    public function testEmptyInputProducesEmptyOutput(): void
    {
        $sut = $this->makeSummarizer([], []);

        self::assertSame([], $sut->summarize([]));
    }

    public function testSingleCustomerSingleProjectSingleType(): void
    {
        $customer = $this->makeCustomer('Acme');
        $project = $this->makeProject('Website', $customer);
        $invoice = $this->makeInvoice(1, $customer, 'USD');
        $milestone = $this->makeMilestone($project, $invoice, '1000.0000', 'CLP');

        $sut = $this->makeSummarizer([$milestone], []);

        $rows = $sut->summarize([$invoice]);

        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertSame('Acme', $row->customerName);
        self::assertSame('Website', $row->projectName);
        self::assertSame(InvoiceHistorySummaryRow::TYPE_MILESTONE, $row->type);
        self::assertSame(1, $row->invoiceCount);
        self::assertSame('1000.0000', $row->amount);
        self::assertSame('CLP', $row->currency);
        self::assertNull($row->durationSeconds);
        self::assertFalse($row->isPartial);
    }

    public function testSameCustomerTwoDifferentProjectsProduceTwoRows(): void
    {
        $customer = $this->makeCustomer('Acme');
        $projectOne = $this->makeProject('Alpha', $customer);
        $projectTwo = $this->makeProject('Beta', $customer);

        $invoiceOne = $this->makeInvoice(1, $customer);
        $invoiceTwo = $this->makeInvoice(2, $customer);

        $milestoneOne = $this->makeMilestone($projectOne, $invoiceOne, '1000.0000', 'CLP');
        $milestoneTwo = $this->makeMilestone($projectTwo, $invoiceTwo, '2000.0000', 'CLP');

        $sut = $this->makeSummarizer([$milestoneOne, $milestoneTwo], []);

        $rows = $sut->summarize([$invoiceOne, $invoiceTwo]);

        self::assertCount(2, $rows);
        self::assertSame('Alpha', $rows[0]->projectName);
        self::assertSame('1000.0000', $rows[0]->amount);
        self::assertSame('Beta', $rows[1]->projectName);
        self::assertSame('2000.0000', $rows[1]->amount);
    }

    public function testSameCustomerAndProjectMixedTypesProduceSeparateRows(): void
    {
        $customer = $this->makeCustomer('Acme');
        $project = $this->makeProject('Website', $customer);

        $milestoneInvoice = $this->makeInvoice(1, $customer, 'CLP');
        $timesheetInvoice = $this->makeInvoice(2, $customer, 'CLP');

        $milestone = $this->makeMilestone($project, $milestoneInvoice, '1000.0000', 'CLP');

        $timesheetSubtotals = [
            ['invoiceId' => 2, 'projectId' => 999, 'projectName' => 'Website', 'amount' => 500.0, 'duration' => 3600],
        ];

        $sut = $this->makeSummarizer([$milestone], $timesheetSubtotals);

        $rows = $sut->summarize([$milestoneInvoice, $timesheetInvoice]);

        self::assertCount(2, $rows);

        $types = array_map(static fn (InvoiceHistorySummaryRow $r) => $r->type, $rows);
        sort($types);
        self::assertSame([InvoiceHistorySummaryRow::TYPE_MILESTONE, InvoiceHistorySummaryRow::TYPE_TIMESHEET], $types);

        foreach ($rows as $row) {
            self::assertSame('Acme', $row->customerName);
            self::assertSame('Website', $row->projectName);
        }
    }

    public function testMultiProjectTimesheetInvoiceProratesAcrossProjects(): void
    {
        $customer = $this->makeCustomer('Acme');
        $invoice = $this->makeInvoice(1, $customer, 'CLP');

        $timesheetSubtotals = [
            ['invoiceId' => 1, 'projectId' => 10, 'projectName' => 'Alpha', 'amount' => 600.0, 'duration' => 3600],
            ['invoiceId' => 1, 'projectId' => 20, 'projectName' => 'Beta', 'amount' => 400.0, 'duration' => 1800],
        ];

        $sut = $this->makeSummarizer([], $timesheetSubtotals);

        $rows = $sut->summarize([$invoice]);

        self::assertCount(2, $rows);

        $amountByProject = [];
        foreach ($rows as $row) {
            self::assertSame(InvoiceHistorySummaryRow::TYPE_TIMESHEET, $row->type);
            self::assertSame(1, $row->invoiceCount);
            $amountByProject[$row->projectName] = (float) $row->amount;
        }

        self::assertSame(600.0, $amountByProject['Alpha']);
        self::assertSame(400.0, $amountByProject['Beta']);
        self::assertSame(1000.0, $amountByProject['Alpha'] + $amountByProject['Beta']);
    }

    public function testMilestoneGroupWithUnconvertibleCurrencyIsPartial(): void
    {
        $customer = $this->makeCustomer('Acme');
        $project = $this->makeProject('Website', $customer);
        $invoice = $this->makeInvoice(1, $customer, 'CLP');

        $milestoneClp = $this->makeMilestone($project, $invoice, '1000.0000', 'CLP');
        $milestoneUnconvertible = $this->makeMilestone($project, $invoice, '5.0000', 'XYZ');

        $sut = $this->makeSummarizer([$milestoneClp, $milestoneUnconvertible], []);

        $rows = $sut->summarize([$invoice]);

        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertTrue($row->isPartial);
        self::assertSame('1000.0000', $row->amount);
    }
}
