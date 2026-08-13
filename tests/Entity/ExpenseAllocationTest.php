<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\ExpenseAllocation;
use App\Entity\Project;
use App\Entity\QuotationLine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpenseAllocation::class)]
class ExpenseAllocationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $sut = new ExpenseAllocation();

        self::assertNull($sut->getId());
        self::assertNull($sut->getExpense());
        self::assertNull($sut->getProject());
        self::assertNull($sut->getPercentage());
        self::assertNull($sut->getAmountClp());
        self::assertFalse($sut->isCharged());
        self::assertNull($sut->getQuotationLine());
    }

    public function testSettersAndGetters(): void
    {
        $project = new Project();
        $sut = (new ExpenseAllocation())
            ->setProject($project)
            ->setPercentage('40.00')
            ->setAmountClp(400000);

        self::assertSame($project, $sut->getProject());
        self::assertSame('40.00', $sut->getPercentage());
        self::assertSame(400000, $sut->getAmountClp());
    }

    public function testMarkChargedSetsQuotationLineAndFlag(): void
    {
        $sut = new ExpenseAllocation();
        $line = new QuotationLine();

        $sut->markCharged($line);

        self::assertTrue($sut->isCharged());
        self::assertSame($line, $sut->getQuotationLine());
    }

    public function testMarkChargedTwiceThrows(): void
    {
        $sut = new ExpenseAllocation();
        $sut->markCharged(new QuotationLine());

        $this->expectException(\DomainException::class);
        $sut->markCharged(new QuotationLine());
    }
}
