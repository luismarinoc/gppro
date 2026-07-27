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
}
