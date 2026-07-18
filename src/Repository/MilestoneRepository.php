<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Repository;

use App\Entity\Milestone;
use App\Entity\Project;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Milestone>
 */
class MilestoneRepository extends EntityRepository
{
    public function saveMilestone(Milestone $milestone): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($milestone);
        $entityManager->flush();
    }

    public function deleteMilestone(Milestone $milestone): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($milestone);
        $entityManager->flush();
    }

    /**
     * @return Milestone[]
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.project = :project')
            ->setParameter('project', $project)
            ->orderBy('m.dueDate', 'ASC')
            ->addOrderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
