<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\FxRate\ClpConversion;
use App\FxRate\ClpConverter;
use App\Invoice\MilestoneInvoiceItem;
use App\Repository\MilestoneInvoiceItemRepository;
use App\Repository\Query\InvoiceQuery;
use App\Repository\Query\MilestoneInvoiceQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MilestoneInvoiceItemRepository::class)]
class MilestoneInvoiceItemRepositoryTest extends TestCase
{
    private function createMilestone(): Milestone
    {
        $milestone = new Milestone();
        $milestone->setProject(new Project());
        $milestone->setName('Design phase');
        $milestone->setValue('1500.0000');
        $milestone->setCurrency('USD');
        $milestone->setDueDate(new \DateTime('2026-07-20'));

        return $milestone;
    }

    public function testGetInvoiceItemsForQueryReturnsEmptyArrayForAnOrdinaryInvoiceQuery(): void
    {
        $converter = $this->createMock(ClpConverter::class);
        $converter->expects($this->never())->method('convert');

        $sut = new MilestoneInvoiceItemRepository($converter);

        $result = $sut->getInvoiceItemsForQuery(new InvoiceQuery());

        self::assertSame([], $result);
    }

    public function testGetInvoiceItemsForQueryBuildsOneItemPerConvertibleMilestone(): void
    {
        $milestone = $this->createMilestone();
        $conversion = ClpConversion::converted('1500.0000', 'USD', '950.1234', new \DateTimeImmutable('2026-07-20'), '1425185.1000');

        $converter = $this->createMock(ClpConverter::class);
        $converter->expects($this->once())->method('convert')
            ->with('1500.0000', 'USD', $this->isInstanceOf(\DateTimeInterface::class))
            ->willReturn($conversion);

        $query = new MilestoneInvoiceQuery();
        $query->setMilestones([$milestone]);

        $sut = new MilestoneInvoiceItemRepository($converter);

        $result = $sut->getInvoiceItemsForQuery($query);

        self::assertCount(1, $result);
        self::assertInstanceOf(MilestoneInvoiceItem::class, $result[0]);
        self::assertSame($milestone, $result[0]->getMilestone());
    }

    public function testGetInvoiceItemsForQuerySkipsMilestoneWhenConversionFails(): void
    {
        $milestone = $this->createMilestone();

        $converter = $this->createMock(ClpConverter::class);
        $converter->expects($this->once())->method('convert')->willReturn(null);

        $query = new MilestoneInvoiceQuery();
        $query->setMilestones([$milestone]);

        $sut = new MilestoneInvoiceItemRepository($converter);

        $result = $sut->getInvoiceItemsForQuery($query);

        self::assertSame([], $result);
    }

    public function testSetExportedIsInertAndNeverThrows(): void
    {
        $converter = $this->createMock(ClpConverter::class);
        $sut = new MilestoneInvoiceItemRepository($converter);

        // Timesheets are exported through TimesheetInvoiceItemRepository, not here.
        $sut->setExported([new Timesheet()]);

        $milestone = $this->createMilestone();
        $item = new MilestoneInvoiceItem($milestone, ClpConversion::identity('1000.0000'));
        $sut->setExported([$item]);

        // No exception raised, and the milestone's FK is untouched here (linking
        // happens exclusively via MilestoneInvoiceLinkSubscriber, Phase 3).
        self::assertFalse($milestone->isInvoiced());
    }
}
