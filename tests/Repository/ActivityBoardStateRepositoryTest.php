<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\Activity;
use App\Entity\ActivityBoardState;
use App\Entity\ActivityBoardStatus;
use App\Entity\Customer;
use App\Entity\Project;
use App\Repository\ActivityBoardStateRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\SQLLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ActivityBoardStateRepository::class)]
#[Group('integration')]
class ActivityBoardStateRepositoryTest extends AbstractRepositoryTestCase
{
    private function getRepository(): ActivityBoardStateRepository
    {
        /** @var ActivityBoardStateRepository $repository */
        $repository = $this->getEntityManager()->getRepository(ActivityBoardState::class);

        return $repository;
    }

    private function createProject(): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Board state test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Board state test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);

        $em->flush();

        return $project;
    }

    private function createActivity(Project $project, string $name = 'Activity'): Activity
    {
        $em = $this->getEntityManager();

        $activity = new Activity();
        $activity->setName($name . ' ' . uniqid());
        $activity->setProject($project);
        $em->persist($activity);
        $em->flush();

        return $activity;
    }

    public function testFindOrCreateReturnsTransientDefaultForActivityWithoutState(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project);

        $state = $this->getRepository()->findOrCreate($activity);

        self::assertNull($state->getId());
        self::assertSame($activity, $state->getActivity());
        self::assertSame(ActivityBoardStatus::TODO, $state->getStatus());
    }

    public function testFindOrCreateReturnsExistingPersistedState(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project);
        $repository = $this->getRepository();

        $state = new ActivityBoardState();
        $state->setActivity($activity);
        $state->setStatus(ActivityBoardStatus::DONE);
        $repository->save($state);

        $em = $this->getEntityManager();
        $em->clear();

        $reloadedActivity = $em->getRepository(Activity::class)->find($activity->getId());
        self::assertNotNull($reloadedActivity);

        $found = $repository->findOrCreate($reloadedActivity);

        self::assertNotNull($found->getId());
        self::assertSame(ActivityBoardStatus::DONE, $found->getStatus());
    }

    public function testFindByActivitiesReturnsOneRowPerActivityInExactlyOneQuery(): void
    {
        $project = $this->createProject();
        $activityWithState = $this->createActivity($project, 'With state');
        $activityWithoutState = $this->createActivity($project, 'Without state');
        $repository = $this->getRepository();

        $state = new ActivityBoardState();
        $state->setActivity($activityWithState);
        $state->setStatus(ActivityBoardStatus::IN_PROGRESS);
        $repository->save($state);

        $em = $this->getEntityManager();
        $em->clear();

        $activityRepository = $em->getRepository(Activity::class);
        $reloadedWithState = $activityRepository->find($activityWithState->getId());
        $reloadedWithoutState = $activityRepository->find($activityWithoutState->getId());
        self::assertNotNull($reloadedWithState);
        self::assertNotNull($reloadedWithoutState);

        // Doctrine\DBAL\Logging\SQLLogger is deprecated in favor of
        // Logging\Middleware, but middlewares are wired once at connection
        // construction time from container config and can't be attached to
        // an already-booted connection - and the profiler-based
        // doctrine.debug_data_holder never collects here because
        // phpunit.xml.dist forces APP_DEBUG=0. SQLLogger is read dynamically
        // per query (Connection::executeQuery() et al.), so it is the only
        // way left to assert an exact query count against a live connection.
        /** @phpstan-ignore class.implementsDeprecatedInterface */
        $logger = new class() implements SQLLogger {
            public int $queryCount = 0;

            public function startQuery($sql, ?array $params = null, ?array $types = null): void
            {
                ++$this->queryCount;
            }

            public function stopQuery(): void
            {
            }
        };

        /** @var Connection $connection */
        $connection = self::getContainer()->get('database_connection');
        $connection->getConfiguration()->setSQLLogger($logger); // @phpstan-ignore method.deprecated

        $result = $repository->findByActivities([$reloadedWithState, $reloadedWithoutState]);

        $connection->getConfiguration()->setSQLLogger(null); // @phpstan-ignore method.deprecated

        self::assertSame(1, $logger->queryCount);
        self::assertCount(1, $result);
        self::assertArrayHasKey($activityWithState->getId(), $result);
        self::assertArrayNotHasKey($activityWithoutState->getId(), $result);
        self::assertSame(ActivityBoardStatus::IN_PROGRESS, $result[$activityWithState->getId()]->getStatus());
    }

    public function testFindByActivitiesReturnsEmptyArrayForEmptyInput(): void
    {
        self::assertSame([], $this->getRepository()->findByActivities([]));
    }
}
