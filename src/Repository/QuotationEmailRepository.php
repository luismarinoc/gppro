<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\QuotationEmail;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<QuotationEmail> */
class QuotationEmailRepository extends EntityRepository
{
    public function findValidToken(string $tokenHash, \DateTimeImmutable $now): ?QuotationEmail
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.tokenHash = :tokenHash')
            ->andWhere('e.expiresAt > :now')
            ->andWhere('e.sentAt IS NOT NULL')
            ->andWhere('e.response IS NULL')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findValidTokenForUpdate(string $tokenHash, \DateTimeImmutable $now): ?QuotationEmail
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.tokenHash = :tokenHash')
            ->andWhere('e.expiresAt > :now')
            ->andWhere('e.sentAt IS NOT NULL')
            ->andWhere('e.response IS NULL')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', $now)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    public function save(QuotationEmail $email): void
    {
        $this->getEntityManager()->persist($email);
        $this->getEntityManager()->flush();
    }
}
