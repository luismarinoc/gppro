<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\ActivityCommentRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'gppro_activities_comments')]
#[ORM\Index(columns: ['activity_id'])]
#[ORM\Entity(repositoryClass: ActivityCommentRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[Serializer\ExclusionPolicy('all')]
class ActivityComment implements CommentInterface
{
    use CommentTableTypeTrait;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Activity $activity;

    public function __construct(Activity $activity)
    {
        $this->createdAt = new \DateTime();
        $this->activity = $activity;
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }
}
