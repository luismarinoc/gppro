<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<Expense> */
class ExpenseRepository extends EntityRepository
{
    public function saveExpense(Expense $expense): void
    {
        $this->getEntityManager()->persist($expense);
        $this->getEntityManager()->flush();
    }

    public function deleteExpense(Expense $expense): void
    {
        $this->getEntityManager()->remove($expense);
        $this->getEntityManager()->flush();
    }

    /** @return Expense[] */
    public function findForListing(?string $status = null): array
    {
        $query = $this->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC');

        if (null !== $status && \in_array($status, Expense::STATUSES, true)) {
            $query->andWhere('e.status = :status')->setParameter('status', $status);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * Expenses pending approval that were not created by the given user
     * (spec: "The expense creator MUST NOT approve any of its own levels").
     * Per-level role matching is enforced by ExpenseApprovalPolicy, not here.
     *
     * @return Expense[]
     */
    public function findPendingForUser(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.createdBy != :user OR e.createdBy IS NULL')
            ->setParameter('status', Expense::STATUS_PENDING_APPROVAL)
            ->setParameter('user', $user)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Original recurring expenses eligible to generate a new period copy.
     * Excludes generated copies themselves (sourceExpense IS NOT NULL) so
     * the recurrence chain never regenerates from a regenerated expense.
     *
     * @return Expense[]
     */
    public function findRecurringSources(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.recurrence = :recurrence')
            ->andWhere('e.sourceExpense IS NULL')
            ->setParameter('recurrence', Expense::RECURRENCE_MONTH)
            ->getQuery()
            ->getResult();
    }
}
