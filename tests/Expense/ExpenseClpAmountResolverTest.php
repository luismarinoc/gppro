<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Expense;

use App\Entity\Expense;
use App\Expense\ExpenseClpAmountResolver;
use App\FxRate\ClpConversion;
use App\FxRate\ClpConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpenseClpAmountResolver::class)]
class ExpenseClpAmountResolverTest extends TestCase
{
    private function makeExpense(int $amount, string $currency, \DateTimeImmutable $expenseDate): Expense
    {
        $expense = new Expense();
        $expense->setDescription('e');
        $expense->setAmount($amount);
        $expense->setCurrency($currency);
        $expense->setExpenseDate($expenseDate);

        return $expense;
    }

    public function testToClpReturnsRawAmountForClpExpense(): void
    {
        $expense = $this->makeExpense(100000, Expense::CURRENCY_CLP, new \DateTimeImmutable('2026-08-01'));

        $converter = $this->createMock(ClpConverter::class);
        $converter->expects(self::once())
            ->method('convert')
            ->with('100000', Expense::CURRENCY_CLP, $expense->getExpenseDate())
            ->willReturn(ClpConversion::identity('100000'));

        $sut = new ExpenseClpAmountResolver($converter);

        self::assertSame(100000, $sut->toClp($expense));
    }

    public function testToClpUsesExpenseDateForUsdConversion(): void
    {
        $expenseDate = new \DateTimeImmutable('2026-08-01');
        $expense = $this->makeExpense(500, Expense::CURRENCY_USD, $expenseDate);

        $converter = $this->createMock(ClpConverter::class);
        $converter->expects(self::once())
            ->method('convert')
            ->with('500', Expense::CURRENCY_USD, $expenseDate)
            ->willReturn(ClpConversion::converted('500', Expense::CURRENCY_USD, '950.0000', $expenseDate, '475000.0000'));

        $sut = new ExpenseClpAmountResolver($converter);

        self::assertSame(475000, $sut->toClp($expense));
    }

    public function testToClpUsesExpenseDateForUfConversion(): void
    {
        $expenseDate = new \DateTimeImmutable('2026-08-01');
        $expense = $this->makeExpense(10, Expense::CURRENCY_UF, $expenseDate);

        $converter = $this->createMock(ClpConverter::class);
        $converter->expects(self::once())
            ->method('convert')
            ->with('10', Expense::CURRENCY_UF, $expenseDate)
            ->willReturn(ClpConversion::converted('10', Expense::CURRENCY_UF, '38000.0000', $expenseDate, '380000.0000'));

        $sut = new ExpenseClpAmountResolver($converter);

        self::assertSame(380000, $sut->toClp($expense));
    }

    public function testToClpReturnsNullWhenNoRateIsAvailable(): void
    {
        $expense = $this->makeExpense(500, Expense::CURRENCY_USD, new \DateTimeImmutable('2026-08-01'));

        $converter = $this->createMock(ClpConverter::class);
        $converter->method('convert')->willReturn(null);

        $sut = new ExpenseClpAmountResolver($converter);

        self::assertNull($sut->toClp($expense));
    }

    public function testIsConvertibleReturnsFalseWhenNoRateIsAvailable(): void
    {
        $expense = $this->makeExpense(500, Expense::CURRENCY_USD, new \DateTimeImmutable('2026-08-01'));

        $converter = $this->createMock(ClpConverter::class);
        $converter->method('convert')->willReturn(null);

        $sut = new ExpenseClpAmountResolver($converter);

        self::assertFalse($sut->isConvertible($expense));
    }

    public function testIsConvertibleReturnsTrueWhenRateExists(): void
    {
        $expenseDate = new \DateTimeImmutable('2026-08-01');
        $expense = $this->makeExpense(500, Expense::CURRENCY_USD, $expenseDate);

        $converter = $this->createMock(ClpConverter::class);
        $converter->method('convert')->willReturn(
            ClpConversion::converted('500', Expense::CURRENCY_USD, '950.0000', $expenseDate, '475000.0000')
        );

        $sut = new ExpenseClpAmountResolver($converter);

        self::assertTrue($sut->isConvertible($expense));
    }
}
