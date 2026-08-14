<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\InvoicePaymentApproval;
use App\Entity\InvoicePaymentApprovalLevel;
use App\Entity\User;
use App\Invoice\InvoicePaymentApprovalLevelResolver;
use App\Invoice\InvoicePaymentApprovalPolicy;
use App\Invoice\InvoicePaymentApprovalService;
use App\Repository\InvoicePaymentApprovalLevelRepository;
use App\Repository\InvoicePaymentApprovalRepository;
use App\Tests\Repository\AbstractRepositoryTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(InvoicePaymentApprovalService::class)]
#[Group('integration')]
class InvoicePaymentApprovalServiceTest extends AbstractRepositoryTestCase
{
    private function getSut(): InvoicePaymentApprovalService
    {
        $em = $this->getEntityManager();
        \assert($em instanceof EntityManagerInterface);

        /** @var InvoicePaymentApprovalLevelRepository $levelRepository */
        $levelRepository = $em->getRepository(InvoicePaymentApprovalLevel::class);
        /** @var InvoicePaymentApprovalRepository $approvalRepository */
        $approvalRepository = $em->getRepository(InvoicePaymentApproval::class);

        return new InvoicePaymentApprovalService(
            $em,
            new InvoicePaymentApprovalLevelResolver($levelRepository),
            new InvoicePaymentApprovalPolicy($levelRepository, $approvalRepository),
        );
    }

    private function addLevel(int $level, float $minAmount, string $requiredRole, ?User $approverUser = null): InvoicePaymentApprovalLevel
    {
        /** @var InvoicePaymentApprovalLevelRepository $repository */
        $repository = $this->getEntityManager()->getRepository(InvoicePaymentApprovalLevel::class);
        $entity = (new InvoicePaymentApprovalLevel())->setLevel($level)->setMinAmount($minAmount)->setRequiredRole($requiredRole)->setApproverUser($approverUser);
        $repository->saveLevel($entity);

        return $entity;
    }

    private function createUser(string ...$roles): User
    {
        $em = $this->getEntityManager();

        $suffix = uniqid();
        $user = new User();
        $user->setUsername('invoice-approval-service-test-' . $suffix);
        $user->setEmail('invoice-approval-service-test-' . $suffix . '@example.com');
        $user->setPassword('irrelevant');
        $user->setEnabled(true);
        foreach ($roles as $role) {
            $user->addRole($role);
        }
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createCustomer(): Customer
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Invoice approval service test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);
        $em->flush();

        return $customer;
    }

    private function createNewInvoice(Customer $customer, User $creator, float $total): Invoice
    {
        $em = $this->getEntityManager();

        $invoice = new Invoice();
        $invoice->setStatus(Invoice::STATUS_NEW);
        $invoice->setTotal($total);
        $invoice->setVat(0.0);
        $invoice->setTax(0.0);
        $invoice->setCustomer($customer);
        $invoice->setInvoiceNumber('inv-approval-service-' . uniqid());
        $invoice->setFilename('inv-approval-service-' . uniqid());
        $invoice->setCreatedAt(new \DateTime());
        $invoice->setUser($creator);
        $invoice->setDueDays(30);
        $invoice->setCurrency('CLP');
        $em->persist($invoice);
        $em->flush();

        return $invoice;
    }

    public function testSubmitComputesAndFreezesRequiredLevels(): void
    {
        $this->addLevel(2, 500000, 'ROLE_ADMIN');
        $customer = $this->createCustomer();
        $creator = $this->createUser();
        $invoice = $this->createNewInvoice($customer, $creator, 1000000.0);

        $submitted = $this->getSut()->submit($invoice);

        self::assertSame(Invoice::PAYMENT_APPROVAL_PENDING, $submitted->getPaymentApprovalStatus());
        self::assertSame(2, $submitted->getPaymentRequiredLevels());
        self::assertSame(0, $submitted->getPaymentCurrentLevel());
    }

    public function testApproveSingleLevelInvoiceRecordsAuditRowAndMovesToApproved(): void
    {
        $customer = $this->createCustomer();
        $creator = $this->createUser();
        $approver = $this->createUser('ROLE_TEAMLEAD');
        $invoice = $this->createNewInvoice($customer, $creator, 100000.0);
        $this->getSut()->submit($invoice);

        $approved = $this->getSut()->approve($invoice, $approver, 'looks good');

        self::assertTrue($approved->isPaymentApproved());
        self::assertSame(1, $approved->getPaymentCurrentLevel());

        /** @var InvoicePaymentApprovalRepository $approvalRepository */
        $approvalRepository = $this->getEntityManager()->getRepository(InvoicePaymentApproval::class);
        $approvals = $approvalRepository->findByInvoice($approved);
        self::assertCount(1, $approvals);
        self::assertSame(InvoicePaymentApproval::DECISION_APPROVED, $approvals[0]->getDecision());
        self::assertSame('looks good', $approvals[0]->getNote());
    }

    public function testApproveByUnauthorizedApproverIsRejected(): void
    {
        $customer = $this->createCustomer();
        $creator = $this->createUser();
        $invoice = $this->createNewInvoice($customer, $creator, 100000.0);
        $this->getSut()->submit($invoice);

        $this->expectException(\DomainException::class);

        $this->getSut()->approve($invoice, $creator);
    }

    public function testTwoLevelInvoiceRequiresBothApproversBeforeApproved(): void
    {
        $this->addLevel(2, 500000, 'ROLE_ADMIN');
        $customer = $this->createCustomer();
        $creator = $this->createUser();
        $teamlead = $this->createUser('ROLE_TEAMLEAD');
        $admin = $this->createUser('ROLE_ADMIN');
        $invoice = $this->createNewInvoice($customer, $creator, 1000000.0);
        $this->getSut()->submit($invoice);

        $afterFirst = $this->getSut()->approve($invoice, $teamlead);
        self::assertFalse($afterFirst->isPaymentApproved());
        self::assertSame(1, $afterFirst->getPaymentCurrentLevel());

        $afterSecond = $this->getSut()->approve($invoice, $admin);
        self::assertTrue($afterSecond->isPaymentApproved());
        self::assertSame(2, $afterSecond->getPaymentCurrentLevel());
    }

    public function testRejectAtSecondLevelDiscardsFirstLevelAndMovesToRejected(): void
    {
        $this->addLevel(2, 500000, 'ROLE_ADMIN');
        $customer = $this->createCustomer();
        $creator = $this->createUser();
        $teamlead = $this->createUser('ROLE_TEAMLEAD');
        $admin = $this->createUser('ROLE_ADMIN');
        $invoice = $this->createNewInvoice($customer, $creator, 1000000.0);
        $this->getSut()->submit($invoice);
        $this->getSut()->approve($invoice, $teamlead);

        $rejected = $this->getSut()->reject($invoice, $admin, 'not valid');

        self::assertSame(Invoice::PAYMENT_APPROVAL_REJECTED, $rejected->getPaymentApprovalStatus());
        self::assertSame(0, $rejected->getPaymentCurrentLevel());

        /** @var InvoicePaymentApprovalRepository $approvalRepository */
        $approvalRepository = $this->getEntityManager()->getRepository(InvoicePaymentApproval::class);
        $approvals = $approvalRepository->findByInvoice($rejected);
        self::assertCount(2, $approvals);
        self::assertSame(InvoicePaymentApproval::DECISION_APPROVED, $approvals[0]->getDecision());
        self::assertSame(InvoicePaymentApproval::DECISION_REJECTED, $approvals[1]->getDecision());
    }

    /**
     * Decision 5 (proposal): submission freezes required levels at the
     * amount current at submit time; a later amount change must not
     * re-evaluate or reopen already-cleared levels.
     */
    public function testAmountIncreaseAfterPartialApprovalDoesNotReopenClearedLevels(): void
    {
        $this->addLevel(2, 500000, 'ROLE_ADMIN');
        $this->addLevel(3, 2000000, 'ROLE_SUPER_ADMIN');
        $customer = $this->createCustomer();
        $creator = $this->createUser();
        $teamlead = $this->createUser('ROLE_TEAMLEAD');
        $invoice = $this->createNewInvoice($customer, $creator, 1000000.0);
        $this->getSut()->submit($invoice);

        $afterFirst = $this->getSut()->approve($invoice, $teamlead);
        self::assertSame(2, $afterFirst->getPaymentRequiredLevels());
        self::assertSame(1, $afterFirst->getPaymentCurrentLevel());

        // amount jumps well past level 3's threshold after submission
        $afterFirst->setTotal(5000000.0);
        $this->getEntityManager()->flush();

        self::assertSame(2, $afterFirst->getPaymentRequiredLevels());
        self::assertSame(1, $afterFirst->getPaymentCurrentLevel());
        self::assertSame(2, $afterFirst->nextPendingPaymentLevel());
    }
}
