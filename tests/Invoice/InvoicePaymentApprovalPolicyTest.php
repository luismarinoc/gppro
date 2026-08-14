<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoicePaymentApprovalLevel;
use App\Entity\User;
use App\Invoice\InvoicePaymentApprovalPolicy;
use App\Repository\InvoicePaymentApprovalLevelRepository;
use App\Repository\InvoicePaymentApprovalRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoicePaymentApprovalPolicy::class)]
class InvoicePaymentApprovalPolicyTest extends TestCase
{
    private function makeUser(string ...$roles): User
    {
        $user = new User();
        $user->setUsername('user-' . uniqid());
        foreach ($roles as $role) {
            $user->addRole($role);
        }

        return $user;
    }

    private function makeLevel(int $level, string $requiredRole, ?User $approverUser = null): InvoicePaymentApprovalLevel
    {
        return (new InvoicePaymentApprovalLevel())
            ->setLevel($level)
            ->setMinAmount(0)
            ->setRequiredRole($requiredRole)
            ->setApproverUser($approverUser);
    }

    private function makePendingInvoice(User $creator, int $requiredLevels = 1): Invoice
    {
        $invoice = new Invoice();
        $invoice->setUser($creator);
        $invoice->submitForPaymentApproval($requiredLevels);

        return $invoice;
    }

    /**
     * @param InvoicePaymentApprovalLevel[] $levels
     */
    private function makeSut(array $levels, bool $userAlreadyApproved = false): InvoicePaymentApprovalPolicy
    {
        $levelRepository = $this->createMock(InvoicePaymentApprovalLevelRepository::class);
        $levelRepository->method('findAllOrdered')->willReturn($levels);

        $approvalRepository = $this->createMock(InvoicePaymentApprovalRepository::class);
        $approvalRepository->method('hasUserApprovedAnyLevel')->willReturn($userAlreadyApproved);

        return new InvoicePaymentApprovalPolicy($levelRepository, $approvalRepository);
    }

    public function testUserWithMatchingRoleCanApproveThePendingLevel(): void
    {
        $creator = $this->makeUser();
        $approver = $this->makeUser('ROLE_TEAMLEAD');
        $invoice = $this->makePendingInvoice($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertTrue($sut->canApprove($invoice, $approver));
    }

    public function testCreatorCannotApproveOwnInvoicePayment(): void
    {
        $creator = $this->makeUser('ROLE_TEAMLEAD');
        $invoice = $this->makePendingInvoice($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertFalse($sut->canApprove($invoice, $creator));
    }

    public function testUserWhoAlreadyClearedALevelCannotClearAnother(): void
    {
        $creator = $this->makeUser();
        $approver = $this->makeUser('ROLE_ADMIN');
        $invoice = $this->makePendingInvoice($creator, 2);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD'), $this->makeLevel(2, 'ROLE_ADMIN')], userAlreadyApproved: true);

        self::assertFalse($sut->canApprove($invoice, $approver));
    }

    public function testSuperAdminApprovesRegardlessOfConfiguredRole(): void
    {
        $creator = $this->makeUser();
        $approver = $this->makeUser('ROLE_SUPER_ADMIN');
        $invoice = $this->makePendingInvoice($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertTrue($sut->canApprove($invoice, $approver));
    }

    public function testUserWithoutTheRequiredRoleCannotApprove(): void
    {
        $creator = $this->makeUser();
        $approver = $this->makeUser('ROLE_USER');
        $invoice = $this->makePendingInvoice($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertFalse($sut->canApprove($invoice, $approver));
    }

    public function testUserWithMatchingRoleCanRejectThePendingLevel(): void
    {
        $creator = $this->makeUser();
        $rejecter = $this->makeUser('ROLE_TEAMLEAD');
        $invoice = $this->makePendingInvoice($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertTrue($sut->canReject($invoice, $rejecter));
    }

    public function testCreatorCannotRejectOwnInvoicePayment(): void
    {
        $creator = $this->makeUser('ROLE_TEAMLEAD');
        $invoice = $this->makePendingInvoice($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertFalse($sut->canReject($invoice, $creator));
    }

    public function testNamedApproverClearsALevelWithoutHoldingTheRole(): void
    {
        $creator = $this->makeUser();
        $namedApprover = $this->makeUser(); // holds no roles
        $invoice = $this->makePendingInvoice($creator);
        $level = $this->makeLevel(1, 'ROLE_TEAMLEAD', $namedApprover);
        $sut = $this->makeSut([$level]);

        self::assertTrue($sut->canApprove($invoice, $namedApprover));
    }

    public function testRoleHolderClearsALevelThatNamesADifferentApprover(): void
    {
        $creator = $this->makeUser();
        $namedApprover = $this->makeUser();
        $roleHolder = $this->makeUser('ROLE_TEAMLEAD');
        $invoice = $this->makePendingInvoice($creator);
        $level = $this->makeLevel(1, 'ROLE_TEAMLEAD', $namedApprover);
        $sut = $this->makeSut([$level]);

        self::assertTrue($sut->canApprove($invoice, $roleHolder));
    }

    /**
     * Branch-ordering invariant (mirrors ExpenseApprovalPolicyTest): the
     * named-approver match must never bypass the creator exclusion.
     */
    public function testCreatorNamedAsApproverIsStillDenied(): void
    {
        $creator = $this->makeUser();
        $invoice = $this->makePendingInvoice($creator);
        $level = $this->makeLevel(1, 'ROLE_TEAMLEAD', $creator);
        $sut = $this->makeSut([$level]);

        self::assertFalse($sut->canApprove($invoice, $creator));
    }

    public function testUnsubmittedInvoiceHasNoApprovableLevel(): void
    {
        $creator = $this->makeUser();
        $approver = $this->makeUser('ROLE_TEAMLEAD');
        $invoice = new Invoice();
        $invoice->setUser($creator);
        $sut = $this->makeSut([$this->makeLevel(1, 'ROLE_TEAMLEAD')]);

        self::assertFalse($sut->canApprove($invoice, $approver));
    }
}
