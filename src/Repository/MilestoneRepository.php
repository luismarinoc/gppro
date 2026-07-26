<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Milestone;
use App\Entity\Project;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Milestone>
 */
class MilestoneRepository extends EntityRepository
{
    public function saveMilestone(Milestone $milestone): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($milestone);
        $entityManager->flush();
    }

    public function deleteMilestone(Milestone $milestone): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($milestone);
        $entityManager->flush();
    }

    /**
     * @return Milestone[]
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.project = :project')
            ->setParameter('project', $project)
            ->orderBy('m.dueDate', 'ASC')
            ->addOrderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Milestones of the given customer that are not yet invoiced and carry a
     * value/currency (the minimum needed to attempt an FX conversion later).
     * Convertibility itself (via ClpConverter) is checked by the caller.
     *
     * @return Milestone[]
     */
    public function findInvoiceableByCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.project', 'p')
            ->andWhere('p.customer = :customer')
            ->andWhere('m.invoice IS NULL')
            ->andWhere('m.value IS NOT NULL')
            ->andWhere('m.currency IS NOT NULL')
            ->setParameter('customer', $customer)
            ->orderBy('m.dueDate', 'ASC')
            ->addOrderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Distinct customers that have at least one not-yet-invoiced milestone
     * with a value/currency (the minimum needed to attempt an FX conversion
     * later; actual convertibility is checked per-milestone by the caller,
     * same split of responsibility as findInvoiceableByCustomer()).
     *
     * @return Customer[]
     */
    public function findCustomersWithInvoiceableMilestones(): array
    {
        $em = $this->getEntityManager();

        $subQuery = $em->createQueryBuilder()
            ->select('1')
            ->from(Milestone::class, 'm')
            ->join('m.project', 'p')
            ->andWhere('p.customer = c')
            ->andWhere('m.invoice IS NULL')
            ->andWhere('m.value IS NOT NULL')
            ->andWhere('m.currency IS NOT NULL');

        $qb = $em->createQueryBuilder();

        return $qb->select('c')
            ->from(Customer::class, 'c')
            ->andWhere($qb->expr()->exists($subQuery->getDQL()))
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Links the given milestones to the invoice, skipping any milestone that
     * is already linked to another invoice (double-invoicing guard under
     * concurrency: the `invoice IS NULL` precondition is enforced by the
     * database, not read-then-write in PHP).
     *
     * @param int[] $milestoneIds
     * @return int number of milestones actually linked (may be less than count($milestoneIds))
     */
    public function markAsInvoiced(Invoice $invoice, array $milestoneIds): int
    {
        if ([] === $milestoneIds) {
            return 0;
        }

        return (int) $this->createQueryBuilder('m')
            ->update()
            ->set('m.invoice', ':invoice')
            ->andWhere('m.id IN (:ids)')
            ->andWhere('m.invoice IS NULL')
            ->setParameter('invoice', $invoice)
            ->setParameter('ids', $milestoneIds)
            ->getQuery()
            ->execute();
    }

    /**
     * Invoice has no inverse `milestones` collection mapped, so "which
     * milestones does this invoice cover" can't be read off the entity
     * directly — this is the cheapest way to answer it for a page of
     * invoice rows. Group the result by getInvoice()->getId() to build a
     * per-invoice list.
     *
     * @param int[] $invoiceIds
     * @return Milestone[]
     */
    public function findByInvoiceIds(array $invoiceIds): array
    {
        if ([] === $invoiceIds) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->andWhere('m.invoice IN (:ids)')
            ->setParameter('ids', $invoiceIds)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
