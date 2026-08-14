<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\ActivityComment;
use Doctrine\ORM\EntityRepository;

/** @extends EntityRepository<ActivityComment> */
class ActivityCommentRepository extends EntityRepository
{
    /**
     * @return array<ActivityComment>
     */
    public function getComments(Activity $activity): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->select('comments')
            ->from(ActivityComment::class, 'comments')
            ->andWhere($qb->expr()->eq('comments.activity', ':activity'))
            ->addOrderBy('comments.pinned', 'DESC')
            ->addOrderBy('comments.createdAt', 'DESC')
            ->setParameter('activity', $activity)
        ;

        return $qb->getQuery()->getResult();
    }

    public function saveComment(ActivityComment $comment): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($comment);
        $entityManager->flush();
    }
}
