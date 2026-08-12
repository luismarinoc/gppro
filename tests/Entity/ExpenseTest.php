<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Entity\Project;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Expense::class)]
class ExpenseTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $sut = new Expense();

        self::assertNull($sut->getId());
        self::assertNull($sut->getDescription());
        self::assertNull($sut->getAmount());
        self::assertNull($sut->getExpenseDate());
        self::assertNull($sut->getRecurrence());
        self::assertSame(Expense::STATUS_DRAFT, $sut->getStatus());
        self::assertNull($sut->getRequiredLevels());
        self::assertSame(0, $sut->getCurrentLevel());
        self::assertNull($sut->getCreatedBy());
        self::assertNull($sut->getSourceExpense());
        self::assertNull($sut->getPeriodKey());
        self::assertCount(0, $sut->getAllocations());
        self::assertCount(0, $sut->getApprovals());
        self::assertNotNull($sut->getCreatedAt());
        self::assertNotNull($sut->getModifiedAt());
        self::assertTrue($sut->isEditable());
        self::assertFalse($sut->isApproved());
        self::assertNull($sut->nextPendingLevel());
    }

    public function testAddAndRemoveAllocationManagesInverseSide(): void
    {
        $sut = new Expense();
        $allocation = (new ExpenseAllocation())->setProject(new Project())->setPercentage('100.00');

        $sut->addAllocation($allocation);

        self::assertCount(1, $sut->getAllocations());
        self::assertSame($sut, $allocation->getExpense());

        $sut->removeAllocation($allocation);

        self::assertCount(0, $sut->getAllocations());
        self::assertNull($allocation->getExpense());
    }

    public function testSubmitForApprovalFreezesRequiredLevelsAndMovesToPendingApproval(): void
    {
        $sut = new Expense();

        $sut->submitForApproval(2);

        self::assertSame(Expense::STATUS_PENDING_APPROVAL, $sut->getStatus());
        self::assertSame(2, $sut->getRequiredLevels());
        self::assertSame(0, $sut->getCurrentLevel());
        self::assertSame(1, $sut->nextPendingLevel());
        self::assertFalse($sut->isEditable());
    }

    public function testSubmitForApprovalFromNonDraftThrows(): void
    {
        $sut = new Expense();
        $sut->submitForApproval(1);

        $this->expectException(\DomainException::class);
        $sut->submitForApproval(1);
    }

    public function testClearLevelInOrderAdvancesCurrentLevel(): void
    {
        $sut = new Expense();
        $sut->submitForApproval(2);

        $sut->clearLevel(1);

        self::assertSame(1, $sut->getCurrentLevel());
        self::assertSame(Expense::STATUS_PENDING_APPROVAL, $sut->getStatus());
        self::assertSame(2, $sut->nextPendingLevel());
    }

    public function testClearingFinalLevelCompletesApproval(): void
    {
        $sut = new Expense();
        $sut->submitForApproval(2);

        $sut->clearLevel(1);
        $sut->clearLevel(2);

        self::assertSame(Expense::STATUS_APPROVED, $sut->getStatus());
        self::assertTrue($sut->isApproved());
        self::assertNull($sut->nextPendingLevel());
    }

    public function testClearLevelOutOfOrderThrows(): void
    {
        $sut = new Expense();
        $sut->submitForApproval(2);

        $this->expectException(\DomainException::class);
        $sut->clearLevel(2);
    }

    public function testClearLevelWhenNotPendingThrows(): void
    {
        $sut = new Expense();

        $this->expectException(\DomainException::class);
        $sut->clearLevel(1);
    }

    public function testRejectApprovalDiscardsClearedLevelsAndMovesToRejected(): void
    {
        $sut = new Expense();
        $sut->submitForApproval(2);
        $sut->clearLevel(1);

        $sut->rejectApproval();

        self::assertSame(Expense::STATUS_REJECTED, $sut->getStatus());
        self::assertSame(0, $sut->getCurrentLevel());
        self::assertNull($sut->nextPendingLevel());
    }

    public function testRejectApprovalWhenNotPendingThrows(): void
    {
        $sut = new Expense();

        $this->expectException(\DomainException::class);
        $sut->rejectApproval();
    }

    public function testApprovedExpenseIsNotEditable(): void
    {
        $sut = new Expense();
        $sut->submitForApproval(1);
        $sut->clearLevel(1);

        self::assertFalse($sut->isEditable());
    }
}
