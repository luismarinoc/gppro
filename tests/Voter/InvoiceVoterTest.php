<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Voter;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\InvoicePaymentApprovalLevel;
use App\Entity\Team;
use App\Entity\User;
use App\Invoice\InvoicePaymentApprovalPolicy;
use App\Repository\InvoicePaymentApprovalLevelRepository;
use App\Repository\InvoicePaymentApprovalRepository;
use App\Security\RolePermissionManager;
use App\Voter\InvoiceVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[CoversClass(InvoiceVoter::class)]
class InvoiceVoterTest extends AbstractVoterTestCase
{
    #[DataProvider('getVoteData')]
    public function testVote(User $user, mixed $subject, string $attribute, int $result): void
    {
        $this->assertVote($user, $subject, $attribute, $result);
    }

    public function testVoteDeniesIfTokenHasNoApplicationUser(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);
        $sut = $this->createVoter();

        self::assertEquals(VoterInterface::ACCESS_DENIED, $sut->vote($token, $this->createInvoice(), ['view_invoice']));
    }

    public function testVoteDeniesIfInvoiceHasNoCustomer(): void
    {
        $this->assertVote(self::getUser(2, User::ROLE_TEAMLEAD), new Invoice(), 'view_invoice', VoterInterface::ACCESS_DENIED);
    }

    public function testVoteRequiresCustomerTeamAccess(): void
    {
        $customer = new Customer('Acme');
        $customer->addTeam(new Team('Accounting'));
        $invoice = $this->createInvoice($customer);

        $this->assertVote(self::getUser(2, User::ROLE_TEAMLEAD), $invoice, 'view_invoice', VoterInterface::ACCESS_DENIED);

        $team = new Team('Accounting');
        $user = new User();
        $user->addRole(User::ROLE_TEAMLEAD);
        $team->addTeamlead($user);

        $customer = new Customer('Acme');
        $customer->addTeam($team);
        $invoice = $this->createInvoice($customer);

        $this->assertVote($user, $invoice, 'view_invoice', VoterInterface::ACCESS_GRANTED);
        $this->assertVote($user, $invoice, 'edit_invoice', VoterInterface::ACCESS_GRANTED);
    }

    public function testDeleteInvoiceRequiresDeletePermission(): void
    {
        $permissions = [
            'ROLE_TEAMLEAD' => ['view_invoice', 'create_invoice', 'delete_invoice'],
        ];

        $team = new Team('Accounting');
        $user = new User();
        $user->addRole(User::ROLE_TEAMLEAD);
        $team->addTeamlead($user);

        $customer = new Customer('Acme');
        $customer->addTeam($team);

        $token = new UsernamePasswordToken($user, 'bar', $user->getRoles());
        $sut = $this->createVoter($this->getRolePermissionManager($permissions, true));

        self::assertEquals(VoterInterface::ACCESS_GRANTED, $sut->vote($token, $this->createInvoice($customer), ['delete_invoice']));
    }

    /**
     * Task 3.1: eligible approver (matching the pending level's required
     * role) is granted, wired through InvoicePaymentApprovalPolicy.
     */
    public function testApproveInvoicePaymentGrantedForEligibleApprover(): void
    {
        $creator = self::getUser(1, User::ROLE_TEAMLEAD);
        $approver = self::getUser(2, User::ROLE_TEAMLEAD);
        $invoice = $this->makePendingInvoice($creator);

        $this->assertApprovalVote($approver, $invoice, 'approve_invoice_payment', VoterInterface::ACCESS_GRANTED, [$this->makeLevel(1, User::ROLE_TEAMLEAD)]);
    }

    /**
     * Task 3.1: ineligible user (no matching role, not the named approver)
     * is denied, wired through InvoicePaymentApprovalPolicy.
     */
    public function testApproveInvoicePaymentDeniedForIneligibleUser(): void
    {
        $creator = self::getUser(1, User::ROLE_TEAMLEAD);
        $ineligible = self::getUser(2, User::ROLE_USER);
        $invoice = $this->makePendingInvoice($creator);

        $this->assertApprovalVote($ineligible, $invoice, 'approve_invoice_payment', VoterInterface::ACCESS_DENIED, [$this->makeLevel(1, User::ROLE_TEAMLEAD)]);
    }

    public function testRejectInvoicePaymentGrantedForEligibleApprover(): void
    {
        $creator = self::getUser(1, User::ROLE_TEAMLEAD);
        $rejecter = self::getUser(2, User::ROLE_TEAMLEAD);
        $invoice = $this->makePendingInvoice($creator);

        $this->assertApprovalVote($rejecter, $invoice, 'reject_invoice_payment', VoterInterface::ACCESS_GRANTED, [$this->makeLevel(1, User::ROLE_TEAMLEAD)]);
    }

    public function testRejectInvoicePaymentDeniedForCreator(): void
    {
        $creator = self::getUser(1, User::ROLE_TEAMLEAD);
        $invoice = $this->makePendingInvoice($creator);

        $this->assertApprovalVote($creator, $invoice, 'reject_invoice_payment', VoterInterface::ACCESS_DENIED, [$this->makeLevel(1, User::ROLE_TEAMLEAD)]);
    }

    private function makeLevel(int $level, string $requiredRole): InvoicePaymentApprovalLevel
    {
        return (new InvoicePaymentApprovalLevel())
            ->setLevel($level)
            ->setMinAmount(0)
            ->setRequiredRole($requiredRole);
    }

    private function makePendingInvoice(User $creator, int $requiredLevels = 1): Invoice
    {
        $invoice = $this->createInvoice(new Customer('Acme'));
        $invoice->setUser($creator);
        $invoice->submitForPaymentApproval($requiredLevels);

        return $invoice;
    }

    /**
     * @param InvoicePaymentApprovalLevel[] $levels
     */
    private function assertApprovalVote(User $user, Invoice $invoice, string $attribute, int $expected, array $levels): void
    {
        $levelRepository = $this->createMock(InvoicePaymentApprovalLevelRepository::class);
        $levelRepository->method('findAllOrdered')->willReturn($levels);

        $approvalRepository = $this->createMock(InvoicePaymentApprovalRepository::class);
        $approvalRepository->method('hasUserApprovedAnyLevel')->willReturn(false);

        $policy = new InvoicePaymentApprovalPolicy($levelRepository, $approvalRepository);
        $voter = $this->createVoter(null, $policy);
        $token = new UsernamePasswordToken($user, 'test', $user->getRoles());

        self::assertSame($expected, $voter->vote($token, $invoice, [$attribute]));
    }

    private function createVoter(?RolePermissionManager $permissions = null, ?InvoicePaymentApprovalPolicy $policy = null): InvoiceVoter
    {
        $permissions ??= $this->getRolePermissionManager();
        $policy ??= new InvoicePaymentApprovalPolicy(
            $this->createMock(InvoicePaymentApprovalLevelRepository::class),
            $this->createMock(InvoicePaymentApprovalRepository::class)
        );

        return new InvoiceVoter($permissions, $policy);
    }

    public static function getVoteData(): \Generator
    {
        $invoice = self::createStaticInvoice();

        yield [self::getUser(0, 'foo'), $invoice, 'view_invoice', VoterInterface::ACCESS_DENIED];
        yield [self::getUser(1, User::ROLE_USER), $invoice, 'view_invoice', VoterInterface::ACCESS_DENIED];
        yield [self::getUser(2, User::ROLE_TEAMLEAD), $invoice, 'view_invoice', VoterInterface::ACCESS_GRANTED];
        yield [self::getUser(2, User::ROLE_TEAMLEAD), $invoice, 'edit_invoice', VoterInterface::ACCESS_GRANTED];
        yield [self::getUser(2, User::ROLE_TEAMLEAD), $invoice, 'delete_invoice', VoterInterface::ACCESS_DENIED];
        yield [self::getUser(3, User::ROLE_ADMIN), $invoice, 'view_invoice', VoterInterface::ACCESS_GRANTED];
        yield [self::getUser(3, User::ROLE_ADMIN), $invoice, 'edit_invoice', VoterInterface::ACCESS_GRANTED];
        yield [self::getUser(3, User::ROLE_ADMIN), $invoice, 'delete_invoice', VoterInterface::ACCESS_DENIED];
        yield [self::getUser(4, User::ROLE_SUPER_ADMIN), $invoice, 'view_invoice', VoterInterface::ACCESS_GRANTED];
        yield [self::getUser(4, User::ROLE_SUPER_ADMIN), $invoice, 'edit_invoice', VoterInterface::ACCESS_GRANTED];
        yield [self::getUser(4, User::ROLE_SUPER_ADMIN), $invoice, 'delete_invoice', VoterInterface::ACCESS_GRANTED];

        $result = VoterInterface::ACCESS_ABSTAIN;
        yield [self::getUser(2, User::ROLE_TEAMLEAD), $invoice, 'view', $result];
        yield [self::getUser(2, User::ROLE_TEAMLEAD), new \stdClass(), 'view_invoice', $result];
        yield [self::getUser(2, User::ROLE_TEAMLEAD), null, 'edit_invoice', $result];
    }

    private function assertVote(User $user, mixed $subject, string $attribute, int $result): void
    {
        $token = new UsernamePasswordToken($user, 'bar', $user->getRoles());
        $sut = $this->createVoter();

        self::assertEquals($result, $sut->vote($token, $subject, [$attribute]));
    }

    private function createInvoice(?Customer $customer = null): Invoice
    {
        $invoice = new Invoice();
        if ($customer !== null) {
            $invoice->setCustomer($customer);
        }

        return $invoice;
    }

    private static function createStaticInvoice(): Invoice
    {
        $invoice = new Invoice();
        $invoice->setCustomer(new Customer('Acme'));

        return $invoice;
    }
}
