<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\QuotationCatalogItem;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<QuotationCatalogItem> */
class QuotationCatalogItemRepository extends EntityRepository
{
    /** @return QuotationCatalogItem[] */
    public function findActive(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.active = :active')
            ->setParameter('active', true)
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return QuotationCatalogItem[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function deleteCatalogItem(QuotationCatalogItem $item): void
    {
        $item->setActive(false);
        $this->saveCatalogItem($item);
    }

    public function saveCatalogItem(QuotationCatalogItem $item): void
    {
        $this->getEntityManager()->persist($item);
        $this->getEntityManager()->flush();
    }
}
