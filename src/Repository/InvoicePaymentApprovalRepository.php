<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\InvoicePaymentApproval;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<InvoicePaymentApproval> */
class InvoicePaymentApprovalRepository extends EntityRepository
{
    /** @return InvoicePaymentApproval[] */
    public function findByInvoice(Invoice $invoice): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('a.level', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Whether the user already cleared/rejected any level of this invoice's
     * payment approval (four-eyes, mirrors ExpenseApprovalRepository::
     * hasUserApprovedAnyLevel()).
     */
    public function hasUserApprovedAnyLevel(Invoice $invoice, User $user): bool
    {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.invoice = :invoice')
            ->andWhere('a.approvedBy = :user')
            ->setParameter('invoice', $invoice)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $count) > 0;
    }
}
