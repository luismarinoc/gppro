<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\Activity;
use App\Entity\ActivityBoardPriority;
use App\Entity\ActivityBoardState;
use App\Entity\ActivityBoardStatus;
use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityBoardState::class)]
class ActivityBoardStateTest extends TestCase
{
    public function testDefaultStatusIsTodoAndOptionalFieldsAreNull(): void
    {
        $sut = new ActivityBoardState();

        self::assertSame(ActivityBoardStatus::TODO, $sut->getStatus());
        self::assertNull($sut->getId());
        self::assertNull($sut->getActivity());
        self::assertNull($sut->getPriority());
        self::assertNull($sut->getDueDate());
        self::assertNull($sut->getAssignedTo());
    }

    public function testActivitySetterAndGetter(): void
    {
        $sut = new ActivityBoardState();
        $activity = new Activity();

        self::assertInstanceOf(ActivityBoardState::class, $sut->setActivity($activity));
        self::assertSame($activity, $sut->getActivity());
    }

    public function testStatusSetterAndGetter(): void
    {
        $sut = new ActivityBoardState();

        self::assertInstanceOf(ActivityBoardState::class, $sut->setStatus(ActivityBoardStatus::IN_REVIEW));
        self::assertSame(ActivityBoardStatus::IN_REVIEW, $sut->getStatus());
    }

    public function testPrioritySetterAndGetterAllowNull(): void
    {
        $sut = new ActivityBoardState();

        self::assertInstanceOf(ActivityBoardState::class, $sut->setPriority(ActivityBoardPriority::URGENT));
        self::assertSame(ActivityBoardPriority::URGENT, $sut->getPriority());

        self::assertInstanceOf(ActivityBoardState::class, $sut->setPriority(null));
        self::assertNull($sut->getPriority());
    }

    public function testDueDateSetterAndGetterAllowNull(): void
    {
        $sut = new ActivityBoardState();
        $dueDate = new \DateTime('2026-08-01');

        self::assertInstanceOf(ActivityBoardState::class, $sut->setDueDate($dueDate));
        self::assertSame($dueDate, $sut->getDueDate());

        self::assertInstanceOf(ActivityBoardState::class, $sut->setDueDate(null));
        self::assertNull($sut->getDueDate());
    }

    public function testAssignedToSetterAndGetterAllowNull(): void
    {
        $sut = new ActivityBoardState();
        $user = new User();

        self::assertInstanceOf(ActivityBoardState::class, $sut->setAssignedTo($user));
        self::assertSame($user, $sut->getAssignedTo());

        self::assertInstanceOf(ActivityBoardState::class, $sut->setAssignedTo(null));
        self::assertNull($sut->getAssignedTo());
    }
}
