<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Widget\Type;

use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\ActivityQuery;
use App\Repository\Query\CustomerQuery;
use App\Repository\Query\ProjectQuery;
use App\Repository\Query\UserQuery;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;

final class OnboardingChecklist extends AbstractWidget
{
    public function __construct(
        private CustomerRepository $customer,
        private ProjectRepository $project,
        private ActivityRepository $activity,
        private TimesheetRepository $timesheet,
        private UserRepository $userRepository
    )
    {
    }

    public function getTitle(): string
    {
        return 'onboarding.title';
    }

    public function getId(): string
    {
        return 'OnboardingChecklist';
    }

    public function getTemplateName(): string
    {
        return 'widget/widget-onboardingchecklist.html.twig';
    }

    /**
     * @param array<string, string|bool|int|null|array<string, mixed>> $options
     * @return array<array{key: string, route: string, done: bool}>
     */
    public function getData(array $options = []): mixed
    {
        $user = $this->getUser();
        $customerQuery = new CustomerQuery();
        $customerQuery->setCurrentUser($user);
        $projectQuery = new ProjectQuery();
        $projectQuery->setCurrentUser($user);
        $activityQuery = new ActivityQuery();
        $activityQuery->setCurrentUser($user);
        $userQuery = new UserQuery();
        $userQuery->setCurrentUser($user);

        return [
            ['key' => 'customer', 'route' => 'admin_customer', 'done' => $this->customer->countCustomersForQuery($customerQuery) > 0],
            ['key' => 'project', 'route' => 'admin_project', 'done' => $this->project->countProjectsForQuery($projectQuery) > 0],
            ['key' => 'activity', 'route' => 'admin_activity', 'done' => $this->activity->countActivitiesForQuery($activityQuery) > 0],
            ['key' => 'timesheet', 'route' => 'timesheet', 'done' => $this->timesheet->count(['user' => $user]) > 0],
            ['key' => 'colleague', 'route' => 'admin_user', 'done' => $this->userRepository->countUsersForQuery($userQuery) > 1],
        ];
    }
}
