<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\InvoicePaymentApprovalLevel;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<InvoicePaymentApprovalLevel> */
class InvoicePaymentApprovalLevelRepository extends EntityRepository
{
    /** @return InvoicePaymentApprovalLevel[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.level', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function saveLevel(InvoicePaymentApprovalLevel $level): void
    {
        $this->getEntityManager()->persist($level);
        $this->getEntityManager()->flush();
    }

    /**
     * Level 1 is the foundational base of the approval ladder (minAmount=0,
     * required so approval works for any submitted invoice) and must never
     * be removed (mirrors Expense spec: "Last remaining level cannot be
     * deleted").
     */
    public function deleteLevel(InvoicePaymentApprovalLevel $level): void
    {
        if (1 === $level->getLevel()) {
            throw new \DomainException('Level 1 cannot be deleted.');
        }

        $this->getEntityManager()->remove($level);
        $this->getEntityManager()->flush();
    }
}
