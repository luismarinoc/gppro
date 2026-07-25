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
}
