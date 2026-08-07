<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\Quotation;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<Quotation> */
class QuotationRepository extends EntityRepository
{
    /** @return Quotation[] */
    public function findByCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Quotation[] */
    public function findForListing(?Customer $customer = null, ?string $status = null): array
    {
        $query = $this->createQueryBuilder('q')
            ->orderBy('q.createdAt', 'DESC');

        if ($customer !== null) {
            $query->andWhere('q.customer = :customer')->setParameter('customer', $customer);
        }

        if ($status !== null && \in_array($status, Quotation::STATUSES, true)) {
            $query->andWhere('q.status = :status')->setParameter('status', $status);
        }

        return $query->getQuery()->getResult();
    }

    public function saveQuotation(Quotation $quotation): void
    {
        $this->getEntityManager()->persist($quotation);
        $this->getEntityManager()->flush();
    }

    public function lockForInvoice(Quotation $quotation): ?Quotation
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.id = :id')
            ->setParameter('id', $quotation->getId())
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }
}
