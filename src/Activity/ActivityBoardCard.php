<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Activity;

use App\Entity\Activity;
use App\Entity\ActivityBoardPriority;
use App\Entity\ActivityBoardState;
use App\Entity\ActivityBoardStatus;
use App\Entity\User;

/**
 * A single board card: an Activity paired with its board state. The state
 * may be transient (default "To Do", never persisted - see design A5) or a
 * persisted ActivityBoardState, the card does not care which.
 */
final class ActivityBoardCard
{
    public function __construct(
        private readonly Activity $activity,
        private readonly ActivityBoardState $state
    ) {
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function getStatus(): ActivityBoardStatus
    {
        return $this->state->getStatus();
    }

    public function getPriority(): ?ActivityBoardPriority
    {
        return $this->state->getPriority();
    }

    public function getDueDate(): ?\DateTime
    {
        return $this->state->getDueDate();
    }

    public function getAssignedTo(): ?User
    {
        return $this->state->getAssignedTo();
    }

    public function getTechnicalUser(): ?User
    {
        return $this->state->getTechnicalUser();
    }

    public function getFunctionalUser(): ?User
    {
        return $this->state->getFunctionalUser();
    }
}
