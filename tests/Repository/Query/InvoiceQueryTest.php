<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository\Query;

use App\Entity\Activity;
use App\Entity\Milestone;
use App\Repository\Query\InvoiceQuery;
use App\Repository\Query\TimesheetQuery;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InvoiceQuery::class)]
#[CoversClass(TimesheetQuery::class)]
class InvoiceQueryTest extends TimesheetQueryTest
{
    /**
     * @param array<Activity> $activities
     */
    private function addActivitiesToMilestone(Milestone $milestone, array $activities): void
    {
        $property = new \ReflectionProperty(Milestone::class, 'activities');
        $property->setAccessible(true);
        $property->setValue($milestone, new ArrayCollection($activities));
    }

    public function testMilestoneResolvesActivitiesWhenNoneSelected(): void
    {
        $activity1 = new Activity();
        $activity2 = new Activity();
        $milestone = new Milestone();
        $this->addActivitiesToMilestone($milestone, [$activity1, $activity2]);

        $sut = new InvoiceQuery();
        self::assertFalse($sut->hasActivities());

        $sut->setMilestone($milestone);

        self::assertSame($milestone, $sut->getMilestone());
        self::assertTrue($sut->hasActivities());
        self::assertSame([$activity1, $activity2], $sut->getActivities());
    }

    public function testMilestoneDoesNotOverwriteExplicitActivities(): void
    {
        $selected = new Activity();
        $milestoneActivity = new Activity();
        $milestone = new Milestone();
        $this->addActivitiesToMilestone($milestone, [$milestoneActivity]);

        $sut = new InvoiceQuery();
        $sut->setActivities([$selected]);
        $sut->setMilestone($milestone);

        self::assertSame([$selected], $sut->getActivities());
    }
    public function testQuery(): void
    {
        $sut = new InvoiceQuery();

        $this->assertPage($sut);
        $this->assertPageSize($sut);
        $this->assertOrderBy($sut, 'begin');
        $this->assertOrder($sut);

        $this->assertUser($sut);
        $this->assertCustomer($sut);
        $this->assertProject($sut);
        $this->assertActivity($sut);

        self::assertEquals(InvoiceQuery::STATE_STOPPED, $sut->getState());
        self::assertFalse($sut->isRunning());
        self::assertTrue($sut->isStopped());

        $this->assertExportedWith($sut, InvoiceQuery::STATE_NOT_EXPORTED);
        $this->assertModifiedAfter($sut);

        self::assertTrue($sut->getBillable());
        self::assertTrue($sut->isBillable());
        self::assertFalse($sut->isNotBillable());
        self::assertFalse($sut->isIgnoreBillable());

        self::assertTrue($sut->isBillable());
        self::assertFalse($sut->isNotBillable());
        $this->assertBillable($sut);
    }
}
