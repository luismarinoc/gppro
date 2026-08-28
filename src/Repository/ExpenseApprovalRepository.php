<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\ExpenseApproval;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<ExpenseApproval> */
class ExpenseApprovalRepository extends EntityRepository
{
    /** @return ExpenseApproval[] */
    public function findByExpense(Expense $expense): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.expense = :expense')
            ->setParameter('expense', $expense)
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Whether the user already cleared/rejected any level of this expense
     * (spec: "A user who cleared one level of an expense MUST NOT clear
     * another level of the same expense").
     */
    public function hasUserApprovedAnyLevel(Expense $expense, User $user): bool
    {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.expense = :expense')
            ->andWhere('a.approvedBy = :user')
            ->setParameter('expense', $expense)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $count) > 0;
    }
}
