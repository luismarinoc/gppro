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
     * @throws ValidationException when assignedTo/technicalUser/functionalUser
     *                             references an unknown user id, or a user without the required
     *                             access: assignedTo requires activity access
     *                             (RolePermissionManager::checkTeamAccessActivity()), while
     *                             technicalUser/functionalUser only require project access
     *                             (RolePermissionManager::checkProjectAccessForActivity())
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
            $state->setAssignedTo($this->resolveActivityUser($activity, $dto->getAssignedToId()));
        }

        if ($dto->hasTechnicalUser()) {
            $state->setTechnicalUser($this->resolveProjectUser($activity, $dto->getTechnicalUserId()));
        }

        if ($dto->hasFunctionalUser()) {
            $state->setFunctionalUser($this->resolveProjectUser($activity, $dto->getFunctionalUserId()));
        }

        $this->stateRepository->save($state);

        return $state;
    }

    /**
     * Resolves and validates a candidate user id for the assignedTo role.
     * A null id clears the assignment. Requires full activity access, via
     * RolePermissionManager::checkTeamAccessActivity() (customer -> project
     * -> activity teams) - no Timesheet or rate table is touched by this
     * validation.
     */
    private function resolveActivityUser(Activity $activity, ?int $userId): ?User
    {
        $candidate = $this->findCandidate($userId);
        if (null === $candidate) {
            return null;
        }

        if (!$this->permissionManager->checkTeamAccessActivity($activity, $candidate)) {
            throw new ValidationException('Assignee does not have access to this activity');
        }

        return $candidate;
    }

    /**
     * Resolves and validates a candidate user id for the technicalUser/
     * functionalUser roles. A null id clears the assignment. Requires only
     * project-level access, via
     * RolePermissionManager::checkProjectAccessForActivity() - deliberately
     * NOT the activity's own `teams` restriction (design decision) - no
     * Timesheet or rate table is touched by this validation.
     */
    private function resolveProjectUser(Activity $activity, ?int $userId): ?User
    {
        $candidate = $this->findCandidate($userId);
        if (null === $candidate) {
            return null;
        }

        if (!$this->permissionManager->checkProjectAccessForActivity($activity, $candidate)) {
            throw new ValidationException('Assignee does not have access to this project');
        }

        return $candidate;
    }

    /**
     * Shared user id lookup for all board assignee roles. A null id means
     * "clear the assignment" and short-circuits to null; a non-null,
     * unknown id throws.
     */
    private function findCandidate(?int $userId): ?User
    {
        if (null === $userId) {
            return null;
        }

        $candidate = $this->userRepository->find($userId);
        if (null === $candidate) {
            throw new ValidationException(\sprintf('Unknown user id "%d"', $userId));
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
