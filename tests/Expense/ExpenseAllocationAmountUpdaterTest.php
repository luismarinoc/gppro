<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Expense\AllocationSplitter;
use App\Expense\ExpenseAllocationAmountUpdater;
use App\Expense\ExpenseClpAmountResolver;
use App\FxRate\ClpConversion;
use App\FxRate\ClpConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpenseAllocationAmountUpdater::class)]
class ExpenseAllocationAmountUpdaterTest extends TestCase
{
    private function makeExpense(array $percentages): Expense
    {
        $expense = new Expense();
        $expense->setDescription('e');
        $expense->setAmount(100000);
        $expense->setCurrency(Expense::CURRENCY_USD);
        $expense->setExpenseDate(new \DateTimeImmutable('2026-08-01'));

        foreach ($percentages as $percentage) {
            $expense->addAllocation((new ExpenseAllocation())->setPercentage($percentage));
        }

        return $expense;
    }

    public function testApplySharesConvertedTotalAcrossAllocationsAndReturnsTrue(): void
    {
        $expense = $this->makeExpense(['60.00', '40.00']);

        $converter = $this->createMock(ClpConverter::class);
        $converter->method('convert')->willReturn(
            ClpConversion::converted('100000', Expense::CURRENCY_USD, '1.0000', $expense->getExpenseDate(), '100000.0000')
        );
        $resolver = new ExpenseClpAmountResolver($converter);

        $sut = new ExpenseAllocationAmountUpdater($resolver, new AllocationSplitter());

        $result = $sut->apply($expense);

        self::assertTrue($result);
        $allocations = $expense->getAllocations()->toArray();
        self::assertSame(60000, $allocations[0]->getAmountClp());
        self::assertSame(40000, $allocations[1]->getAmountClp());
    }

    public function testApplyClearsAllAmountsToNullAndReturnsFalseWhenNotConvertible(): void
    {
        $expense = $this->makeExpense(['100.00']);
        $expense->getAllocations()->first()->setAmountClp(12345); // stale value from an earlier conversion

        $converter = $this->createMock(ClpConverter::class);
        $converter->method('convert')->willReturn(null);
        $resolver = new ExpenseClpAmountResolver($converter);

        $sut = new ExpenseAllocationAmountUpdater($resolver, new AllocationSplitter());

        $result = $sut->apply($expense);

        self::assertFalse($result);
        self::assertNull($expense->getAllocations()->first()->getAmountClp());
    }
}
