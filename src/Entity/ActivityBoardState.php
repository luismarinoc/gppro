<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\ActivityBoardStateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Board-specific state for an Activity (status/priority/due date/assignee).
 *
 * Unidirectional OneToOne from this entity to Activity - Activity itself
 * carries no relation back and no new columns, so it is never touched by
 * this feature (see design decision A1/A5).
 */
#[ORM\Table(name: 'gppro_activities_board_state')]
#[ORM\UniqueConstraint(name: 'UNIQ_GPPRO_ACTIVITIES_BOARD_ACTIVITY', columns: ['activity_id'])]
#[ORM\Index(name: 'IDX_GPPRO_ACTIVITIES_BOARD_USER', columns: ['assigned_to'])]
#[ORM\Entity(repositoryClass: ActivityBoardStateRepository::class)]
class ActivityBoardState
{
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Activity $activity = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 20, nullable: false, enumType: ActivityBoardStatus::class)]
    #[Assert\NotNull]
    private ActivityBoardStatus $status = ActivityBoardStatus::TODO;

    #[ORM\Column(name: 'priority', type: Types::STRING, length: 20, nullable: true, enumType: ActivityBoardPriority::class)]
    private ?ActivityBoardPriority $priority = null;

    #[ORM\Column(name: 'due_date', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dueDate = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'assigned_to', nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedTo = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(Activity $activity): ActivityBoardState
    {
        $this->activity = $activity;

        return $this;
    }

    public function getStatus(): ActivityBoardStatus
    {
        return $this->status;
    }

    public function setStatus(ActivityBoardStatus $status): ActivityBoardState
    {
        $this->status = $status;

        return $this;
    }

    public function getPriority(): ?ActivityBoardPriority
    {
        return $this->priority;
    }

    public function setPriority(?ActivityBoardPriority $priority): ActivityBoardState
    {
        $this->priority = $priority;

        return $this;
    }

    public function getDueDate(): ?\DateTime
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTime $dueDate): ActivityBoardState
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getAssignedTo(): ?User
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(?User $assignedTo): ActivityBoardState
    {
        $this->assignedTo = $assignedTo;

        return $this;
    }
}
