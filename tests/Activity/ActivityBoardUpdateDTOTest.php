<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Activity;

use App\Activity\ActivityBoardUpdateDTO;
use App\Entity\ActivityBoardPriority;
use App\Entity\ActivityBoardStatus;
use App\Validator\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityBoardUpdateDTO::class)]
class ActivityBoardUpdateDTOTest extends TestCase
{
    public function testFromArrayWithEmptyArrayHasNoFieldsPresent(): void
    {
        $dto = ActivityBoardUpdateDTO::fromArray([]);

        self::assertFalse($dto->hasStatus());
        self::assertFalse($dto->hasPriority());
        self::assertFalse($dto->hasDueDate());
        self::assertFalse($dto->hasAssignedTo());
    }

    public function testFromArrayParsesAllFieldsWhenPresentWithValidValues(): void
    {
        $dto = ActivityBoardUpdateDTO::fromArray([
            'status' => 'in_progress',
            'priority' => 'high',
            'dueDate' => '2026-08-01',
            'assignedTo' => 42,
        ]);

        self::assertTrue($dto->hasStatus());
        self::assertSame(ActivityBoardStatus::IN_PROGRESS, $dto->getStatus());

        self::assertTrue($dto->hasPriority());
        self::assertSame(ActivityBoardPriority::HIGH, $dto->getPriority());

        self::assertTrue($dto->hasDueDate());
        self::assertNotNull($dto->getDueDate());
        self::assertSame('2026-08-01', $dto->getDueDate()->format('Y-m-d'));

        self::assertTrue($dto->hasAssignedTo());
        self::assertSame(42, $dto->getAssignedToId());
    }

    public function testFromArrayThrowsForUnknownStatusValue(): void
    {
        $this->expectException(ValidationException::class);

        ActivityBoardUpdateDTO::fromArray(['status' => 'not_a_real_status']);
    }

    public function testFromArrayThrowsWhenStatusIsExplicitNull(): void
    {
        $this->expectException(ValidationException::class);

        ActivityBoardUpdateDTO::fromArray(['status' => null]);
    }

    public function testFromArrayThrowsForUnknownPriorityValue(): void
    {
        $this->expectException(ValidationException::class);

        ActivityBoardUpdateDTO::fromArray(['priority' => 'not_a_real_priority']);
    }

    public function testFromArrayThrowsForUnparseableDueDate(): void
    {
        $this->expectException(ValidationException::class);

        ActivityBoardUpdateDTO::fromArray(['dueDate' => 'not-a-date']);
    }

    public function testFromArrayThrowsForNonIntegerAssignedTo(): void
    {
        $this->expectException(ValidationException::class);

        ActivityBoardUpdateDTO::fromArray(['assignedTo' => 'forty-two']);
    }

    public function testFromArrayAllowsExplicitNullToClearPriorityDueDateAndAssignedTo(): void
    {
        $dto = ActivityBoardUpdateDTO::fromArray([
            'priority' => null,
            'dueDate' => null,
            'assignedTo' => null,
        ]);

        self::assertTrue($dto->hasPriority());
        self::assertNull($dto->getPriority());

        self::assertTrue($dto->hasDueDate());
        self::assertNull($dto->getDueDate());

        self::assertTrue($dto->hasAssignedTo());
        self::assertNull($dto->getAssignedToId());
    }
}
