<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\LoginAttempt;
use App\Repository\Paginator\PaginatorInterface;
use App\Repository\Paginator\QueryPaginator;
use App\Repository\Query\LoginAttemptQuery;
use App\Utils\Pagination;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends EntityRepository<LoginAttempt>
 */
class LoginAttemptRepository extends EntityRepository
{
    public function saveLoginAttempt(LoginAttempt $loginAttempt): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($loginAttempt);
        $entityManager->flush();
    }

    private function getQueryBuilderForQuery(LoginAttemptQuery $query): QueryBuilder
    {
        $qb = $this->createQueryBuilder('la');
        $qb->addOrderBy('la.' . $query->getOrderBy(), $query->getOrder());

        if ($query->getUser() !== null) {
            $qb->andWhere('la.user = :user')->setParameter('user', $query->getUser());
        }

        if ($query->getOutcome() !== null) {
            $qb->andWhere('la.outcome = :outcome')->setParameter('outcome', $query->getOutcome());
        }

        if ($query->getDateFrom() !== null) {
            $qb->andWhere('la.createdAt >= :dateFrom')->setParameter('dateFrom', $query->getDateFrom(), Types::DATETIME_IMMUTABLE);
        }

        if ($query->getDateTo() !== null) {
            $qb->andWhere('la.createdAt <= :dateTo')->setParameter('dateTo', $query->getDateTo(), Types::DATETIME_IMMUTABLE);
        }

        return $qb;
    }

    /**
     * @return PaginatorInterface<LoginAttempt>
     */
    private function getPaginatorForQuery(LoginAttemptQuery $query): PaginatorInterface
    {
        $qb = $this->getQueryBuilderForQuery($query);

        $counterQb = clone $qb;
        $counterQb
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select($counterQb->expr()->countDistinct('la.id'))
        ;
        /** @var int<0, max> $counter */
        $counter = (int) $counterQb->getQuery()->getSingleScalarResult();

        /** @var Query<LoginAttempt> $orderedQuery */
        $orderedQuery = $qb->getQuery();

        return new QueryPaginator($orderedQuery, $counter);
    }

    public function getPagerfantaForQuery(LoginAttemptQuery $query): Pagination
    {
        return new Pagination($this->getPaginatorForQuery($query), $query);
    }
}
