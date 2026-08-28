<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\Expense;
use App\Entity\ExpenseApproval;
use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

#[CoversClass(ExpenseApproval::class)]
class ExpenseApprovalTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $sut = new ExpenseApproval();

        self::assertNull($sut->getId());
        self::assertNull($sut->getExpense());
        self::assertNull($sut->getLevel());
        self::assertSame(1, $sut->getApprovalAttempt());
        self::assertNull($sut->getDecision());
        self::assertNull($sut->getApprovedBy());
        self::assertNull($sut->getApprovedAt());
        self::assertNull($sut->getNote());
    }

    public function testSettersAndGetters(): void
    {
        $expense = new Expense();
        $user = new User();
        $approvedAt = new \DateTimeImmutable();

        $sut = (new ExpenseApproval())
            ->setExpense($expense)
            ->setLevel(1)
            ->setApprovalAttempt(2)
            ->setDecision(ExpenseApproval::DECISION_APPROVED)
            ->setApprovedBy($user)
            ->setApprovedAt($approvedAt)
            ->setNote('Looks good');

        self::assertSame($expense, $sut->getExpense());
        self::assertSame(1, $sut->getLevel());
        self::assertSame(2, $sut->getApprovalAttempt());
        self::assertSame(ExpenseApproval::DECISION_APPROVED, $sut->getDecision());
        self::assertSame($user, $sut->getApprovedBy());
        self::assertSame($approvedAt, $sut->getApprovedAt());
        self::assertSame('Looks good', $sut->getNote());
    }

    public function testValidatorRejectsAnUnknownDecision(): void
    {
        $sut = (new ExpenseApproval())
            ->setExpense(new Expense())
            ->setLevel(1)
            ->setApprovedAt(new \DateTimeImmutable());

        $reflection = new \ReflectionProperty(ExpenseApproval::class, 'decision');
        $reflection->setValue($sut, 'maybe');

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        self::assertNotEmpty($validator->validate($sut));
    }
}
