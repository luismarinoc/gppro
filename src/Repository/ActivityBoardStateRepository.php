<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\ActivityBoardState;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ActivityBoardState>
 */
class ActivityBoardStateRepository extends EntityRepository
{
    /**
     * One row per activity that already has board state, keyed by activity
     * id, in exactly one query. Activities without a row are simply absent
     * here - callers materialize a transient "To Do" default via
     * findOrCreate() instead of backfilling rows (design decision A5).
     *
     * @param Activity[] $activities
     * @return array<int, ActivityBoardState>
     */
    public function findByActivities(array $activities): array
    {
        if ([] === $activities) {
            return [];
        }

        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.activity IN (:activities)')
            ->setParameter('activities', $activities)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $activityId = $row->getActivity()?->getId();
            if (null !== $activityId) {
                $map[$activityId] = $row;
            }
        }

        return $map;
    }

    /**
     * Returns the existing state for $activity, or a new transient (not
     * persisted) default "To Do" state - zero write amplification until
     * something is actually changed (design decision A5).
     */
    public function findOrCreate(Activity $activity): ActivityBoardState
    {
        $existing = $this->findOneBy(['activity' => $activity]);

        if (null !== $existing) {
            return $existing;
        }

        return (new ActivityBoardState())->setActivity($activity);
    }

    public function save(ActivityBoardState $state): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($state);
        $entityManager->flush();
    }
}
