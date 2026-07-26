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
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\MilestoneRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(MilestoneRepository::class)]
#[Group('integration')]
class MilestoneRepositoryTest extends AbstractRepositoryTestCase
{
    private function getRepository(): MilestoneRepository
    {
        /** @var MilestoneRepository $repository */
        $repository = $this->getEntityManager()->getRepository(Milestone::class);

        return $repository;
    }

    private function createProject(?Customer $customer = null): Project
    {
        $em = $this->getEntityManager();

        if (null === $customer) {
            $customer = new Customer('Milestone repository test customer ' . uniqid());
            $customer->setCountry('CL');
            $customer->setTimezone('America/Santiago');
            $em->persist($customer);
        }

        $project = new Project();
        $project->setName('Milestone repository test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);

        $em->flush();

        return $project;
    }

    private function createCustomer(): Customer
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Milestone repository test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);
        $em->flush();

        return $customer;
    }

    private function createUser(): User
    {
        $em = $this->getEntityManager();

        $suffix = uniqid();
        $user = new User();
        $user->setUsername('milestone-repo-test-' . $suffix);
        $user->setEmail('milestone-repo-test-' . $suffix . '@example.com');
        $user->setPassword('irrelevant');
        $user->setEnabled(true);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createInvoice(Customer $customer, User $user): Invoice
    {
        $em = $this->getEntityManager();

        $suffix = uniqid();
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->setUser($user);
        $invoice->setInvoiceNumber('INV-' . $suffix);
        $invoice->setFilename('invoice-' . $suffix);
        $invoice->setCreatedAt(new \DateTime());
        $invoice->setCurrency('CLP');
        $invoice->setTotal(1000.00);
        $invoice->setVat(0.0);
        $invoice->setTax(0.0);
        $invoice->setDueDays(30);
        $em->persist($invoice);
        $em->flush();

        return $invoice;
    }

    private function createMilestone(Project $project, string $name = 'Milestone'): Milestone
    {
        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName($name . ' ' . uniqid());
        $milestone->setValue('1000.0000');
        $milestone->setCurrency('USD');

        $this->getRepository()->saveMilestone($milestone);

        return $milestone;
    }

    public function testValueAndCurrencyRoundTripWithoutPrecisionLoss(): void
    {
        $em = $this->getEntityManager();
        $repository = $this->getRepository();
        $project = $this->createProject();

        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName('Milestone with value');
        $milestone->setValue('5000.1234');
        $milestone->setCurrency('USD');
        $repository->saveMilestone($milestone);

        $id = $milestone->getId();
        $em->clear();

        $stored = $repository->find($id);

        self::assertNotNull($stored);
        self::assertSame('5000.1234', $stored->getValue());
        self::assertSame('USD', $stored->getCurrency());
    }

    public function testValueAndCurrencyPersistAsNullByDefault(): void
    {
        $em = $this->getEntityManager();
        $repository = $this->getRepository();
        $project = $this->createProject();

        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName('Milestone without value');
        $repository->saveMilestone($milestone);

        $id = $milestone->getId();
        $em->clear();

        $stored = $repository->find($id);

        self::assertNotNull($stored);
        self::assertNull($stored->getValue());
        self::assertNull($stored->getCurrency());
    }

    public function testFindInvoiceableByCustomerExcludesInvoicedAndOtherCustomer(): void
    {
        $repository = $this->getRepository();
        $customer = $this->createCustomer();
        $otherCustomer = $this->createCustomer();
        $project = $this->createProject($customer);
        $otherProject = $this->createProject($otherCustomer);
        $user = $this->createUser();
        $invoice = $this->createInvoice($customer, $user);

        $invoiceable = $this->createMilestone($project, 'Invoiceable');

        $alreadyInvoiced = $this->createMilestone($project, 'Already invoiced');
        $alreadyInvoiced->setInvoice($invoice);
        $repository->saveMilestone($alreadyInvoiced);

        $otherCustomerMilestone = $this->createMilestone($otherProject, 'Other customer');

        $result = $repository->findInvoiceableByCustomer($customer);
        $resultIds = array_map(static fn (Milestone $m): ?int => $m->getId(), $result);

        self::assertContains($invoiceable->getId(), $resultIds);
        self::assertNotContains($alreadyInvoiced->getId(), $resultIds);
        self::assertNotContains($otherCustomerMilestone->getId(), $resultIds);
    }

    public function testFindInvoiceableByCustomerIncludesMultiProjectSameCustomer(): void
    {
        $repository = $this->getRepository();
        $customer = $this->createCustomer();
        $projectA = $this->createProject($customer);
        $projectB = $this->createProject($customer);

        $milestoneA = $this->createMilestone($projectA, 'Project A milestone');
        $milestoneB = $this->createMilestone($projectB, 'Project B milestone');

        $result = $repository->findInvoiceableByCustomer($customer);
        $resultIds = array_map(static fn (Milestone $m): ?int => $m->getId(), $result);

        self::assertContains($milestoneA->getId(), $resultIds);
        self::assertContains($milestoneB->getId(), $resultIds);
    }

    public function testMarkAsInvoicedSkipsAlreadyLinkedRows(): void
    {
        $em = $this->getEntityManager();
        $repository = $this->getRepository();
        $customer = $this->createCustomer();
        $project = $this->createProject($customer);
        $user = $this->createUser();
        $firstInvoice = $this->createInvoice($customer, $user);
        $secondInvoice = $this->createInvoice($customer, $user);

        $freeMilestone = $this->createMilestone($project, 'Free');

        $claimedMilestone = $this->createMilestone($project, 'Claimed');
        $claimedMilestone->setInvoice($firstInvoice);
        $repository->saveMilestone($claimedMilestone);

        $freeId = $freeMilestone->getId();
        $claimedId = $claimedMilestone->getId();
        self::assertNotNull($freeId);
        self::assertNotNull($claimedId);

        $affected = $repository->markAsInvoiced($secondInvoice, [$freeId, $claimedId]);

        self::assertSame(1, $affected);

        $em->clear();

        $reloadedFree = $repository->find($freeId);
        $reloadedClaimed = $repository->find($claimedId);

        self::assertNotNull($reloadedFree);
        self::assertNotNull($reloadedClaimed);

        $reloadedFreeInvoice = $reloadedFree->getInvoice();
        $reloadedClaimedInvoice = $reloadedClaimed->getInvoice();

        self::assertNotNull($reloadedFreeInvoice);
        self::assertNotNull($reloadedClaimedInvoice);
        self::assertSame($secondInvoice->getId(), $reloadedFreeInvoice->getId());
        self::assertSame($firstInvoice->getId(), $reloadedClaimedInvoice->getId());
    }

    public function testDeletingInvoiceReleasesMilestonesViaDatabaseSetNull(): void
    {
        $em = $this->getEntityManager();
        $repository = $this->getRepository();
        $customer = $this->createCustomer();
        $project = $this->createProject($customer);
        $user = $this->createUser();
        $invoice = $this->createInvoice($customer, $user);

        $milestone = $this->createMilestone($project, 'Linked');
        $milestone->setInvoice($invoice);
        $repository->saveMilestone($milestone);

        $milestoneId = $milestone->getId();

        $invoiceToDelete = $em->getRepository(Invoice::class)->find($invoice->getId());
        self::assertNotNull($invoiceToDelete);
        $em->remove($invoiceToDelete);
        $em->flush();
        $em->clear();

        $released = $repository->find($milestoneId);

        self::assertNotNull($released);
        self::assertNull($released->getInvoice());
        self::assertFalse($released->isInvoiced());
    }

    public function testFindCustomersWithInvoiceableMilestonesExcludesInvoicedOnlyAndValuelessCustomers(): void
    {
        $repository = $this->getRepository();

        $customerWithInvoiceable = $this->createCustomer();
        $projectWithInvoiceable = $this->createProject($customerWithInvoiceable);
        $this->createMilestone($projectWithInvoiceable, 'Invoiceable');

        $customerFullyInvoiced = $this->createCustomer();
        $projectFullyInvoiced = $this->createProject($customerFullyInvoiced);
        $user = $this->createUser();
        $invoice = $this->createInvoice($customerFullyInvoiced, $user);
        $invoicedMilestone = $this->createMilestone($projectFullyInvoiced, 'Already invoiced');
        $invoicedMilestone->setInvoice($invoice);
        $repository->saveMilestone($invoicedMilestone);

        $customerWithoutValue = $this->createCustomer();
        $projectWithoutValue = $this->createProject($customerWithoutValue);
        $milestoneWithoutValue = new Milestone();
        $milestoneWithoutValue->setProject($projectWithoutValue);
        $milestoneWithoutValue->setName('No value ' . uniqid());
        $repository->saveMilestone($milestoneWithoutValue);

        $result = $repository->findCustomersWithInvoiceableMilestones();
        $resultIds = array_map(static fn (Customer $c): ?int => $c->getId(), $result);

        self::assertContains($customerWithInvoiceable->getId(), $resultIds);
        self::assertNotContains($customerFullyInvoiced->getId(), $resultIds);
        self::assertNotContains($customerWithoutValue->getId(), $resultIds);
    }

    public function testFindCustomersWithInvoiceableMilestonesReturnsCustomerOnceForMultipleProjects(): void
    {
        $repository = $this->getRepository();
        $customer = $this->createCustomer();
        $projectA = $this->createProject($customer);
        $projectB = $this->createProject($customer);
        $this->createMilestone($projectA, 'Project A milestone');
        $this->createMilestone($projectB, 'Project B milestone');

        $result = $repository->findCustomersWithInvoiceableMilestones();
        $resultIds = array_map(static fn (Customer $c): ?int => $c->getId(), $result);
        $matches = array_filter($resultIds, static fn (?int $id): bool => $id === $customer->getId());

        self::assertCount(1, $matches);
    }
}
