<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Validator\Constraints;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Validator\Constraints\TimesheetDeactivated;
use App\Validator\Constraints\TimesheetDeactivatedValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<TimesheetDeactivatedValidator>
 */
#[CoversClass(TimesheetDeactivated::class)]
#[CoversClass(TimesheetDeactivatedValidator::class)]
class TimesheetDeactivatedValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): TimesheetDeactivatedValidator
    {
        return new TimesheetDeactivatedValidator();
    }

    public function testConstraintIsInvalid(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new Timesheet(), new NotBlank());
    }

    public function testInvalidValueThrowsException(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new NotBlank(), new TimesheetDeactivated(['message' => 'myMessage']));
    }

    public function testDisabledValuesDuringStart(): void
    {
        $begin = new \DateTime('-10 hour');
        $customer = new Customer('foo');
        $customer->setVisible(false);
        $activity = new Activity();
        $activity->setVisible(false);
        $project = new Project();
        $project->setVisible(false);
        $project->setCustomer($customer);
        $activity->setProject($project);

        $timesheet = new Timesheet();
        $timesheet
            ->setBegin($begin)
            ->setActivity($activity)
            ->setProject($project)
        ;

        $this->validator->validate($timesheet, new TimesheetDeactivated(['message' => 'myMessage']));

        $this->buildViolation(TimesheetDeactivated::getErrorName(TimesheetDeactivated::DISABLED_ACTIVITY_ERROR))
            ->atPath('property.path.activity')
            ->setCode(TimesheetDeactivated::DISABLED_ACTIVITY_ERROR)
            ->buildNextViolation(TimesheetDeactivated::getErrorName(TimesheetDeactivated::DISABLED_PROJECT_ERROR))
            ->atPath('property.path.project')
            ->setCode(TimesheetDeactivated::DISABLED_PROJECT_ERROR)
            ->buildNextViolation(TimesheetDeactivated::getErrorName(TimesheetDeactivated::DISABLED_CUSTOMER_ERROR))
            ->atPath('property.path.customer')
            ->setCode(TimesheetDeactivated::DISABLED_CUSTOMER_ERROR)
            ->assertRaised();
    }

    public function testMilestoneInvoicedBlocksStartingNewTime(): void
    {
        $begin = new \DateTime('-10 hour');
        $customer = new Customer('foo');
        $project = new Project();
        $project->setCustomer($customer);

        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName('Invoiced milestone');
        $milestone->setInvoice(new Invoice());

        $activity = new Activity();
        $activity->setProject($project);
        $activity->setMilestone($milestone);

        $timesheet = new Timesheet();
        $timesheet
            ->setBegin($begin)
            ->setActivity($activity)
            ->setProject($project)
        ;

        $this->validator->validate($timesheet, new TimesheetDeactivated(['message' => 'myMessage']));

        $this->buildViolation(TimesheetDeactivated::getErrorName(TimesheetDeactivated::MILESTONE_INVOICED_ERROR))
            ->atPath('property.path.activity')
            ->setCode(TimesheetDeactivated::MILESTONE_INVOICED_ERROR)
            ->assertRaised();
    }

    public function testMilestoneNotInvoicedDoesNotBlockStartingNewTime(): void
    {
        $begin = new \DateTime('-10 hour');
        $customer = new Customer('foo');
        $project = new Project();
        $project->setCustomer($customer);

        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName('Not yet invoiced milestone');

        $activity = new Activity();
        $activity->setProject($project);
        $activity->setMilestone($milestone);

        $timesheet = new Timesheet();
        $timesheet
            ->setBegin($begin)
            ->setActivity($activity)
            ->setProject($project)
        ;

        $this->validator->validate($timesheet, new TimesheetDeactivated(['message' => 'myMessage']));

        $this->assertNoViolation();
    }

    public function testMilestoneInvoicedDoesNotBlockEditingAnAlreadySavedTimesheet(): void
    {
        $begin = new \DateTime('-10 hour');
        $end = new \DateTime('-9 hour');
        $customer = new Customer('foo');
        $project = new Project();
        $project->setCustomer($customer);

        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName('Invoiced milestone');
        $milestone->setInvoice(new Invoice());

        $activity = new Activity();
        $activity->setProject($project);
        $activity->setMilestone($milestone);

        $timesheet = new Timesheet();
        $timesheet
            ->setBegin($begin)
            ->setEnd($end)
            ->setActivity($activity)
            ->setProject($project)
        ;

        $idProperty = new \ReflectionProperty(Timesheet::class, 'id');
        $idProperty->setValue($timesheet, 1);

        $this->validator->validate($timesheet, new TimesheetDeactivated(['message' => 'myMessage']));

        $this->assertNoViolation();
    }

    public function testGetTargets(): void
    {
        $constraint = new TimesheetDeactivated();
        self::assertEquals('class', $constraint->getTargets());
    }
}
