<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice;

use App\Entity\Invoice;
use App\Entity\Milestone;
use App\Entity\Project;
use App\FxRate\ClpConversion;
use App\Invoice\MilestoneInvoiceItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MilestoneInvoiceItem::class)]
class MilestoneInvoiceItemTest extends TestCase
{
    private function createMilestone(string $name = 'Design phase'): Milestone
    {
        $milestone = new Milestone();
        $milestone->setProject(new Project());
        $milestone->setName($name);
        $milestone->setValue('1500.0000');
        $milestone->setCurrency('USD');

        return $milestone;
    }

    public function testBeginAndEndUseDueDateNormalizedToMidnight(): void
    {
        $milestone = $this->createMilestone();
        $milestone->setDueDate(new \DateTime('2026-07-20 15:30:00'));

        $sut = new MilestoneInvoiceItem($milestone, $this->convertedConversion());

        self::assertEquals(new \DateTime('2026-07-20 00:00:00'), $sut->getBegin());
        self::assertEquals(new \DateTime('2026-07-20 00:00:00'), $sut->getEnd());
    }

    public function testBeginAndEndFallBackToCreatedAtWhenDueDateIsNull(): void
    {
        $milestone = $this->createMilestone();
        // dueDate intentionally left null; createdAt is always set by the constructor
        $createdAt = $milestone->getCreatedAt();
        self::assertNotNull($createdAt);

        $sut = new MilestoneInvoiceItem($milestone, $this->convertedConversion());

        $expected = \DateTime::createFromInterface($createdAt);
        $expected->setTime(0, 0, 0);

        self::assertEquals($expected, $sut->getBegin());
        self::assertEquals($expected, $sut->getEnd());
    }

    public function testGetBeginReturnsAFreshCloneEveryCallNeverTheEntityInstance(): void
    {
        $milestone = $this->createMilestone();
        $milestone->setDueDate(new \DateTime('2026-07-20 00:00:00'));

        $sut = new MilestoneInvoiceItem($milestone, $this->convertedConversion());

        $begin = $sut->getBegin();
        $begin->modify('+10 years');

        // Mutating the returned instance must never leak back into the entity
        self::assertEquals(new \DateTime('2026-07-20 00:00:00'), $milestone->getDueDate());

        // A second call must return an independent clone too, not the mutated one
        $secondBegin = $sut->getBegin();
        self::assertEquals(new \DateTime('2026-07-20 00:00:00'), $secondBegin);
        self::assertNotSame($begin, $secondBegin);
    }

    public function testFixedRateAndRateEqualClpAmountAndAmountIsOne(): void
    {
        $milestone = $this->createMilestone();
        $conversion = ClpConversion::converted('1500.0000', 'USD', '950.1234', new \DateTimeImmutable('2026-07-20'), '1425185.1000');

        $sut = new MilestoneInvoiceItem($milestone, $conversion);

        self::assertSame(1425185.1, $sut->getFixedRate());
        self::assertSame(1425185.1, $sut->getRate());
        self::assertSame(1.0, $sut->getAmount());
    }

    public function testHourlyRateAndDurationAndInternalRateAreNull(): void
    {
        $sut = new MilestoneInvoiceItem($this->createMilestone(), $this->convertedConversion());

        self::assertNull($sut->getHourlyRate());
        self::assertNull($sut->getDuration());
        self::assertNull($sut->getInternalRate());
    }

    public function testIsExportedTracksTheInvoiceForeignKey(): void
    {
        $milestone = $this->createMilestone();
        $sut = new MilestoneInvoiceItem($milestone, $this->convertedConversion());

        self::assertFalse($sut->isExported());

        $milestone->setInvoice(new Invoice());

        self::assertTrue($sut->isExported());
    }

    public function testMetaFieldsAreAlwaysEmpty(): void
    {
        $sut = new MilestoneInvoiceItem($this->createMilestone(), $this->convertedConversion());

        self::assertSame([], $sut->getVisibleMetaFields());
        self::assertCount(0, $sut->getMetaFields());
        self::assertNull($sut->getMetaField('anything'));
    }

    public function testDescriptionCarriesCurrencyRateAndClpAmountForAConvertedMilestone(): void
    {
        $milestone = $this->createMilestone('Design phase');
        $conversion = ClpConversion::converted('1500.0000', 'USD', '950.1234', new \DateTimeImmutable('2026-07-20'), '1425185.1000');

        $sut = new MilestoneInvoiceItem($milestone, $conversion);

        $description = $sut->getDescription();

        self::assertStringContainsString('Design phase', $description);
        self::assertStringContainsString('1500.0000', $description);
        self::assertStringContainsString('USD', $description);
        self::assertStringContainsString('950.1234', $description);
        self::assertStringContainsString('2026-07-20', $description);
        self::assertStringContainsString('1425185.1000', $description);
        self::assertStringContainsString('CLP', $description);
    }

    public function testDescriptionForClpIdentityConversionOmitsRateButKeepsAmount(): void
    {
        $milestone = $this->createMilestone('CLP milestone');
        $conversion = ClpConversion::identity('500000.0000');

        $sut = new MilestoneInvoiceItem($milestone, $conversion);

        $description = $sut->getDescription();

        self::assertStringContainsString('CLP milestone', $description);
        self::assertStringContainsString('500000.0000', $description);
        self::assertStringContainsString('CLP', $description);
        // no FX rate was looked up for a CLP->CLP passthrough
        self::assertStringNotContainsString('×', $description);
    }

    public function testTypeAndCategoryAreMilestone(): void
    {
        $sut = new MilestoneInvoiceItem($this->createMilestone(), $this->convertedConversion());

        self::assertSame('milestone', $sut->getType());
        self::assertSame('milestone', $sut->getCategory());
    }

    public function testIsBillableIsAlwaysTrue(): void
    {
        $sut = new MilestoneInvoiceItem($this->createMilestone(), $this->convertedConversion());

        self::assertTrue($sut->isBillable());
    }

    public function testProjectIsDelegatedToTheMilestoneActivityAndUserAreNull(): void
    {
        $milestone = $this->createMilestone();
        $project = $milestone->getProject();

        $sut = new MilestoneInvoiceItem($milestone, $this->convertedConversion());

        self::assertSame($project, $sut->getProject());
        self::assertNull($sut->getActivity());
        self::assertNull($sut->getUser());
    }

    public function testGetIdAndGetMilestoneDelegateToTheWrappedEntity(): void
    {
        $milestone = $this->createMilestone();

        $sut = new MilestoneInvoiceItem($milestone, $this->convertedConversion());

        self::assertSame($milestone->getId(), $sut->getId());
        self::assertSame($milestone, $sut->getMilestone());
    }

    public function testGetTagsAsArrayIsAlwaysEmpty(): void
    {
        $sut = new MilestoneInvoiceItem($this->createMilestone(), $this->convertedConversion());

        self::assertSame([], $sut->getTagsAsArray());
    }

    private function convertedConversion(): ClpConversion
    {
        return ClpConversion::converted('1500.0000', 'USD', '950.1234', new \DateTimeImmutable('2026-07-20'), '1425185.1000');
    }
}
