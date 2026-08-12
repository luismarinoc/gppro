<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\ExpenseApprovalLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpenseApprovalLevel::class)]
class ExpenseApprovalLevelTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $sut = new ExpenseApprovalLevel();

        self::assertNull($sut->getId());
        self::assertNull($sut->getLevel());
        self::assertNull($sut->getMinAmount());
        self::assertNull($sut->getRequiredRole());
    }

    public function testSettersAndGetters(): void
    {
        $sut = (new ExpenseApprovalLevel())
            ->setLevel(2)
            ->setMinAmount(1000000)
            ->setRequiredRole('ROLE_ADMIN');

        self::assertSame(2, $sut->getLevel());
        self::assertSame(1000000, $sut->getMinAmount());
        self::assertSame('ROLE_ADMIN', $sut->getRequiredRole());
    }
}
