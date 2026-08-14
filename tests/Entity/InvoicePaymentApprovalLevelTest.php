<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\InvoicePaymentApprovalLevel;
use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoicePaymentApprovalLevel::class)]
class InvoicePaymentApprovalLevelTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $sut = new InvoicePaymentApprovalLevel();

        self::assertNull($sut->getId());
        self::assertNull($sut->getLevel());
        self::assertNull($sut->getMinAmount());
        self::assertNull($sut->getRequiredRole());
        self::assertNull($sut->getApproverUser());
    }

    public function testSettersAndGetters(): void
    {
        $sut = (new InvoicePaymentApprovalLevel())
            ->setLevel(2)
            ->setMinAmount(1_000_000.50)
            ->setRequiredRole('ROLE_ADMIN');

        self::assertSame(2, $sut->getLevel());
        self::assertSame(1_000_000.50, $sut->getMinAmount());
        self::assertSame('ROLE_ADMIN', $sut->getRequiredRole());
    }

    /**
     * D5: minAmount is float (matches Invoice::getTotal(): float), not int
     * like Expense's whole-CLP amount.
     */
    public function testMinAmountAcceptsFractionalFloat(): void
    {
        $sut = (new InvoicePaymentApprovalLevel())->setMinAmount(500_000.25);

        self::assertSame(500_000.25, $sut->getMinAmount());
    }

    public function testApproverUserSetterAcceptsUserAndNull(): void
    {
        $approver = new User();
        $sut = new InvoicePaymentApprovalLevel();

        $sut->setApproverUser($approver);
        self::assertSame($approver, $sut->getApproverUser());

        $sut->setApproverUser(null);
        self::assertNull($sut->getApproverUser());
    }
}
