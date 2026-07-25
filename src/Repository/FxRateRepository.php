<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\FxRate;
use App\Repository\Paginator\PaginatorInterface;
use App\Repository\Paginator\QueryPaginator;
use App\Repository\Query\FxRateQuery;
use App\Utils\Pagination;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends EntityRepository<FxRate>
 */
class FxRateRepository extends EntityRepository
{
    public function findOneByDateAndIndicator(\DateTimeImmutable $date, string $indicator): ?FxRate
    {
        return $this->findOneBy(['date' => $date, 'indicator' => $indicator]);
    }

    public function saveFxRate(FxRate $fxRate): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($fxRate);
        $entityManager->flush();
    }

    public function deleteFxRate(FxRate $fxRate): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($fxRate);
        $entityManager->flush();
    }

    /**
     * Persists $value for the given (date, indicator).
     *
     * Without $force, an existing row is left untouched and false is returned —
     * this is the idempotency guarantee the daily sync relies on. With $force,
     * the existing value is replaced and modifiedAt is refreshed.
     */
    public function upsert(\DateTimeImmutable $date, string $indicator, string $value, bool $force): bool
    {
        $existing = $this->findOneByDateAndIndicator($date, $indicator);

        if ($existing !== null && !$force) {
            return false;
        }

        $fxRate = $existing ?? new FxRate();

        if ($existing === null) {
            $fxRate->setDate($date);
            $fxRate->setIndicator($indicator);
        }

        $fxRate->setRateValue($value);
        $fxRate->markAsModified();

        $this->saveFxRate($fxRate);

        return true;
    }

    private function getQueryBuilderForQuery(FxRateQuery $query): QueryBuilder
    {
        $qb = $this->createQueryBuilder('f');
        $qb->addOrderBy('f.' . $query->getOrderBy(), $query->getOrder());

        if ($query->getBegin() !== null) {
            $qb->andWhere('f.date >= :begin')->setParameter('begin', $query->getBegin());
        }

        if ($query->getEnd() !== null) {
            $qb->andWhere('f.date <= :end')->setParameter('end', $query->getEnd());
        }

        if ($query->getIndicator() !== null) {
            $qb->andWhere('f.indicator = :indicator')->setParameter('indicator', $query->getIndicator());
        }

        return $qb;
    }

    /**
     * @return PaginatorInterface<FxRate>
     */
    private function getPaginatorForQuery(FxRateQuery $query): PaginatorInterface
    {
        $qb = $this->getQueryBuilderForQuery($query);

        $counterQb = clone $qb;
        $counterQb
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select($counterQb->expr()->countDistinct('f.id'))
        ;
        /** @var int<0, max> $counter */
        $counter = (int) $counterQb->getQuery()->getSingleScalarResult();

        /** @var Query<FxRate> $orderedQuery */
        $orderedQuery = $qb->getQuery();

        return new QueryPaginator($orderedQuery, $counter);
    }

    public function getPagerfantaForQuery(FxRateQuery $query): Pagination
    {
        return new Pagination($this->getPaginatorForQuery($query), $query);
    }
}
