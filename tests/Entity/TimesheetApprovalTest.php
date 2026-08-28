<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\TimesheetApproval;
use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimesheetApproval::class)]
class TimesheetApprovalTest extends TestCase
{
    public function testDecisionAuditFields(): void
    {
        $user = new User();
        $at = new \DateTimeImmutable();
        $approval = (new TimesheetApproval())
            ->setApprovalAttempt(2)
            ->setDecision(TimesheetApproval::DECISION_REJECTED)
            ->setDecidedBy($user)
            ->setDecidedAt($at)
            ->setNote('Please correct the duration.');

        self::assertSame(2, $approval->getApprovalAttempt());
        self::assertSame(TimesheetApproval::DECISION_REJECTED, $approval->getDecision());
        self::assertSame($user, $approval->getDecidedBy());
        self::assertSame($at, $approval->getDecidedAt());
        self::assertSame('Please correct the duration.', $approval->getNote());
    }
}
