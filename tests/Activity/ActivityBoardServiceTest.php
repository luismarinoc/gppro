<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Activity;

use App\Activity\ActivityBoardService;
use App\Activity\ActivityBoardUpdateDTO;
use App\Entity\Activity;
use App\Entity\ActivityBoardState;
use App\Entity\ActivityBoardStatus;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ActivityBoardStateRepository;
use App\Repository\ActivityRepository;
use App\Tests\Repository\AbstractRepositoryTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ActivityBoardService::class)]
#[Group('integration')]
class ActivityBoardServiceTest extends AbstractRepositoryTestCase
{
    private function getSut(): ActivityBoardService
    {
        $em = $this->getEntityManager();

        /** @var ActivityRepository $activityRepository */
        $activityRepository = $em->getRepository(Activity::class);
        /** @var ActivityBoardStateRepository $stateRepository */
        $stateRepository = $em->getRepository(ActivityBoardState::class);

        return new ActivityBoardService($activityRepository, $stateRepository);
    }

    private function createProject(): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Board service test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Board service test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);

        $em->flush();

        return $project;
    }

    private function createActivity(?Project $project, string $name = 'Activity', bool $visible = true): Activity
    {
        $em = $this->getEntityManager();

        $activity = new Activity();
        $activity->setName($name . ' ' . uniqid());
        $activity->setProject($project);
        $activity->setVisible($visible);
        $em->persist($activity);
        $em->flush();

        return $activity;
    }

    public function testCreateBoardPutsStatelessActivitiesInTodoColumn(): void
    {
        $project = $this->createProject();
        $this->createActivity($project, 'No state yet');

        $columns = $this->getSut()->createBoard($project, new User());

        self::assertArrayHasKey(ActivityBoardStatus::TODO->value, $columns);
        self::assertCount(1, $columns[ActivityBoardStatus::TODO->value]->getCards());
        self::assertCount(0, $columns[ActivityBoardStatus::IN_PROGRESS->value]->getCards());
        self::assertCount(0, $columns[ActivityBoardStatus::IN_REVIEW->value]->getCards());
        self::assertCount(0, $columns[ActivityBoardStatus::DONE->value]->getCards());
    }

    public function testCreateBoardExcludesHiddenActivities(): void
    {
        $project = $this->createProject();
        $this->createActivity($project, 'Visible activity', true);
        $this->createActivity($project, 'Hidden activity', false);

        $columns = $this->getSut()->createBoard($project, new User());

        $names = array_map(
            static fn ($card) => $card->getActivity()->getName(),
            $columns[ActivityBoardStatus::TODO->value]->getCards()
        );

        self::assertCount(1, $names);
        self::assertNotNull($names[0]);
        self::assertStringStartsWith('Visible activity', $names[0]);
    }

    public function testCreateBoardExcludesGlobalActivities(): void
    {
        $project = $this->createProject();
        $this->createActivity($project, 'Project-owned activity');
        $this->createActivity(null, 'Global activity');

        $columns = $this->getSut()->createBoard($project, new User());

        $names = array_map(
            static fn ($card) => $card->getActivity()->getName(),
            $columns[ActivityBoardStatus::TODO->value]->getCards()
        );

        self::assertCount(1, $names);
        self::assertNotNull($names[0]);
        self::assertStringStartsWith('Project-owned activity', $names[0]);
    }

    public function testUpdateCardPersistsPartialStatusChangeOnly(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card to update');
        $sut = $this->getSut();

        $dto = ActivityBoardUpdateDTO::fromArray(['status' => 'in_progress']);
        $sut->updateCard($activity, $dto);

        $em = $this->getEntityManager();
        $em->clear();

        $reloadedActivity = $em->getRepository(Activity::class)->find($activity->getId());
        self::assertNotNull($reloadedActivity);

        /** @var ActivityBoardStateRepository $stateRepository */
        $stateRepository = $em->getRepository(ActivityBoardState::class);
        $state = $stateRepository->findOrCreate($reloadedActivity);

        self::assertNotNull($state->getId());
        self::assertSame(ActivityBoardStatus::IN_PROGRESS, $state->getStatus());
        self::assertNull($state->getPriority());
    }

    public function testUpdateCardExplicitNullClearsPreviouslySetPriorityAndDueDate(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card with priority');
        $sut = $this->getSut();

        $sut->updateCard($activity, ActivityBoardUpdateDTO::fromArray([
            'priority' => 'urgent',
            'dueDate' => '2026-08-01',
        ]));

        $sut->updateCard($activity, ActivityBoardUpdateDTO::fromArray([
            'priority' => null,
            'dueDate' => null,
        ]));

        $em = $this->getEntityManager();
        $em->clear();

        $reloadedActivity = $em->getRepository(Activity::class)->find($activity->getId());
        self::assertNotNull($reloadedActivity);

        /** @var ActivityBoardStateRepository $stateRepository */
        $stateRepository = $em->getRepository(ActivityBoardState::class);
        $state = $stateRepository->findOrCreate($reloadedActivity);

        self::assertNull($state->getPriority());
        self::assertNull($state->getDueDate());
    }
}
