<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Repository\Query\InvoiceArchiveQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(InvoiceRepository::class)]
#[Group('integration')]
class InvoiceRepositoryTest extends AbstractRepositoryTestCase
{
    private function createCustomer(string $name): Customer
    {
        $em = $this->getEntityManager();

        $customer = new Customer($name . ' ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);
        $em->flush();

        return $customer;
    }

    private function createInvoice(Customer $customer, \DateTime $createdAt): Invoice
    {
        $em = $this->getEntityManager();

        $invoice = new Invoice();
        $invoice->setStatus(Invoice::STATUS_NEW);
        $invoice->setTotal(100.0);
        $invoice->setVat(0.0);
        $invoice->setTax(0.0);
        $invoice->setCustomer($customer);
        $invoice->setInvoiceNumber('inv-repo-' . uniqid());
        $invoice->setFilename('inv-repo-' . uniqid());
        $invoice->setCreatedAt($createdAt);
        $invoice->setUser($this->getUserByRole(User::ROLE_USER));
        $invoice->setDueDays(30);
        $invoice->setCurrency('CLP');
        $em->persist($invoice);
        $em->flush();

        return $invoice;
    }

    public function testInvoicesAreOrderedByCustomerNameFirstEvenWithInterleavedDates(): void
    {
        $em = $this->getEntityManager();
        /** @var InvoiceRepository $repository */
        $repository = $em->getRepository(Invoice::class);

        // "Zzz..." sorts after "Aaa..." alphabetically, but gets the more
        // recent createdAt date, so a naive date-desc sort would interleave
        // the two customers' invoices instead of grouping them together.
        $customerA = $this->createCustomer('Aaa customer');
        $customerZ = $this->createCustomer('Zzz customer');

        $older = new \DateTime('-2 days');
        $newer = new \DateTime('-1 day');

        $invoiceZOld = $this->createInvoice($customerZ, clone $older);
        $invoiceANew = $this->createInvoice($customerA, clone $newer);
        $invoiceZNew = $this->createInvoice($customerZ, clone $newer);
        $invoiceAOld = $this->createInvoice($customerA, clone $older);

        $query = new InvoiceArchiveQuery();
        $query->addCustomer($customerA);
        $query->addCustomer($customerZ);

        $result = array_values(iterator_to_array($repository->getInvoicesForQuery($query)));

        $ids = array_map(static fn (Invoice $invoice) => $invoice->getId(), $result);

        $expectedZoneA = [$invoiceANew->getId(), $invoiceAOld->getId()];
        $expectedZoneZ = [$invoiceZNew->getId(), $invoiceZOld->getId()];

        self::assertCount(4, $ids);

        // customer "Aaa" must be fully grouped before customer "Zzz"
        $positionsA = array_intersect($ids, $expectedZoneA);
        $positionsZ = array_intersect($ids, $expectedZoneZ);

        self::assertSame($expectedZoneA, array_values($positionsA), 'Customer A invoices must keep the date DESC order within the customer group');
        self::assertSame($expectedZoneZ, array_values($positionsZ), 'Customer Z invoices must keep the date DESC order within the customer group');

        self::assertSame(array_merge($expectedZoneA, $expectedZoneZ), $ids, 'Invoices must be grouped by customer name ASC before applying the date ordering');
    }

    /**
     * Task 4.3: deliberately naive at the repository layer, mirroring
     * ExpenseRepository::findPendingForUser() (design's explicitly flagged
     * gap) - only excludes the invoice's own creator, does NOT filter by
     * approver-level eligibility. That filtering is the dashboard
     * controller's responsibility (task 4.6/4.7).
     */
    public function testFindPendingPaymentApprovalForUserExcludesOnlyOwnInvoicesNotApproverEligibility(): void
    {
        $em = $this->getEntityManager();
        /** @var InvoiceRepository $repository */
        $repository = $em->getRepository(Invoice::class);

        $customer = $this->createCustomer('Dashboard repo invoice customer');
        $creator = $this->getUserByRole(User::ROLE_ADMIN);
        $otherUser = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $ownInvoice = $this->createInvoice($customer, new \DateTime('-1 day'));
        $ownInvoice->setUser($otherUser);
        $ownInvoice->submitForPaymentApproval(1);
        $em->persist($ownInvoice);
        $em->flush();

        $othersInvoice = $this->createInvoice($customer, new \DateTime('-1 day'));
        $othersInvoice->setUser($creator);
        $othersInvoice->submitForPaymentApproval(1);
        $em->persist($othersInvoice);
        $em->flush();

        $notSubmittedInvoice = $this->createInvoice($customer, new \DateTime('-1 day'));
        $notSubmittedInvoice->setUser($creator);
        $em->flush();

        // Raw repository result for $otherUser: excludes their own invoice,
        // but does NOT check whether $otherUser is actually an eligible
        // approver for $othersInvoice's pending level (that check happens
        // one layer up, in the controller).
        $result = $repository->findPendingPaymentApprovalForUser($otherUser);
        $ids = array_map(static fn (Invoice $i) => $i->getId(), $result);

        self::assertContains($othersInvoice->getId(), $ids, 'A pending invoice not created by the caller must be included, regardless of approver eligibility');
        self::assertNotContains($ownInvoice->getId(), $ids, 'The caller\'s own invoice must be excluded');
        self::assertNotContains($notSubmittedInvoice->getId(), $ids, 'An invoice never submitted for payment approval must be excluded');
    }

    /**
     * Approvals bell badge (design A5/Interfaces): the COUNT-only sibling
     * must mirror findPendingPaymentApprovalForUser()'s naive scope exactly
     * (creator exclusion only) and return a scalar int.
     */
    public function testCountPendingPaymentApprovalForUserMatchesNaiveScope(): void
    {
        $em = $this->getEntityManager();
        /** @var InvoiceRepository $repository */
        $repository = $em->getRepository(Invoice::class);

        $customer = $this->createCustomer('Dashboard repo invoice count customer');
        $creator = $this->getUserByRole(User::ROLE_ADMIN);
        $otherUser = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $ownInvoice = $this->createInvoice($customer, new \DateTime('-1 day'));
        $ownInvoice->setUser($creator);
        $ownInvoice->submitForPaymentApproval(1);
        $em->persist($ownInvoice);
        $em->flush();

        $othersInvoiceOne = $this->createInvoice($customer, new \DateTime('-1 day'));
        $othersInvoiceOne->setUser($otherUser);
        $othersInvoiceOne->submitForPaymentApproval(1);
        $em->persist($othersInvoiceOne);
        $em->flush();

        $othersInvoiceTwo = $this->createInvoice($customer, new \DateTime('-1 day'));
        $othersInvoiceTwo->setUser($otherUser);
        $othersInvoiceTwo->submitForPaymentApproval(1);
        $em->persist($othersInvoiceTwo);
        $em->flush();

        self::assertSame(2, $repository->countPendingPaymentApprovalForUser($creator));
    }
}
