<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Activity;

use App\Entity\Activity;
use App\Entity\ActivityBoardState;
use App\Entity\ActivityBoardStatus;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ActivityBoardStateRepository;
use App\Repository\ActivityRepository;
use App\Repository\Query\ActivityQuery;
use App\Repository\Query\VisibilityInterface;
use App\Repository\UserRepository;
use App\Security\RolePermissionManager;
use App\Validator\ValidationException;

/**
 * @final
 */
class ActivityBoardService
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly ActivityBoardStateRepository $stateRepository,
        private readonly UserRepository $userRepository,
        private readonly RolePermissionManager $permissionManager
    ) {
    }

    /**
     * Builds the 4 board columns for a project: one query for the
     * permission-scoped, visible, non-global activities of the project, one
     * query for their existing board states - state-less activities are
     * materialized as a transient "To Do" card (design A5).
     *
     * @return array<string, ActivityBoardColumn> keyed by ActivityBoardStatus value
     */
    public function createBoard(Project $project, User $user): array
    {
        $query = new ActivityQuery();
        $query->addProject($project);
        $query->setExcludeGlobals(true);
        $query->setVisibility(VisibilityInterface::SHOW_VISIBLE);
        $query->setCurrentUser($user);

        $activities = $this->activityRepository->getActivitiesForQuery($query);
        $states = $this->stateRepository->findByActivities($activities);

        /** @var array<string, ActivityBoardCard[]> $cardsByStatus */
        $cardsByStatus = [];
        foreach (ActivityBoardStatus::cases() as $status) {
            $cardsByStatus[$status->value] = [];
        }

        foreach ($activities as $activity) {
            $state = $this->resolveState($activity, $states);
            $card = new ActivityBoardCard($activity, $state);
            $cardsByStatus[$state->getStatus()->value][] = $card;
        }

        $columns = [];
        foreach (ActivityBoardStatus::cases() as $status) {
            $columns[$status->value] = new ActivityBoardColumn($status, $cardsByStatus[$status->value]);
        }

        return $columns;
    }

    /**
     * Applies a partial update to an activity's board state. Only the
     * fields present in the DTO are touched (see
     * ActivityBoardUpdateDTO::has*()); this method writes to
     * ActivityBoardState exclusively and never to Activity, Timesheet, or
     * any billing-related table (design's central non-goal invariant).
     *
     * @throws ValidationException when assignedTo references an unknown
     *                             user id, or a user without access to the activity's project
     *                             (RolePermissionManager::checkTeamAccessActivity())
     */
    public function updateCard(Activity $activity, ActivityBoardUpdateDTO $dto): ActivityBoardState
    {
        $state = $this->stateRepository->findOrCreate($activity);

        $status = $dto->getStatus();
        if ($dto->hasStatus() && null !== $status) {
            $state->setStatus($status);
        }

        if ($dto->hasPriority()) {
            $state->setPriority($dto->getPriority());
        }

        if ($dto->hasDueDate()) {
            $state->setDueDate($dto->getDueDate());
        }

        if ($dto->hasAssignedTo()) {
            $state->setAssignedTo($this->resolveAssignee($activity, $dto->getAssignedToId()));
        }

        $this->stateRepository->save($state);

        return $state;
    }

    /**
     * Resolves and validates a candidate assignee id. A null id clears the
     * assignment. Deliberately reads only User/Activity/Project/Customer/Team
     * relations (via RolePermissionManager::checkTeamAccessActivity()) - no
     * Timesheet or rate table is touched by this validation.
     */
    private function resolveAssignee(Activity $activity, ?int $assignedToId): ?User
    {
        if (null === $assignedToId) {
            return null;
        }

        $candidate = $this->userRepository->find($assignedToId);
        if (null === $candidate) {
            throw new ValidationException(\sprintf('Unknown user id "%d"', $assignedToId));
        }

        if (!$this->permissionManager->checkTeamAccessActivity($activity, $candidate)) {
            throw new ValidationException('Assignee does not have access to this activity');
        }

        return $candidate;
    }

    /**
     * @param array<int, ActivityBoardState> $states
     */
    private function resolveState(Activity $activity, array $states): ActivityBoardState
    {
        $activityId = $activity->getId();
        if (null !== $activityId && isset($states[$activityId])) {
            return $states[$activityId];
        }

        return (new ActivityBoardState())->setActivity($activity);
    }
}
