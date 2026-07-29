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
use App\Entity\Team;
use App\Entity\User;
use App\Repository\ActivityBoardStateRepository;
use App\Repository\ActivityRepository;
use App\Repository\UserRepository;
use App\Security\RolePermissionManager;
use App\Tests\Repository\AbstractRepositoryTestCase;
use App\Validator\ValidationException;
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
        /** @var UserRepository $userRepository */
        $userRepository = $em->getRepository(User::class);
        /** @var RolePermissionManager $permissionManager */
        $permissionManager = self::getContainer()->get(RolePermissionManager::class);

        return new ActivityBoardService($activityRepository, $stateRepository, $userRepository, $permissionManager);
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

    public function testUpdateCardPersistsAssignedToWhenCandidateHasProjectTeamAccess(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card to assign');
        $candidate = $this->getUserByRole(User::ROLE_TEAMLEAD);

        // candidate is a plain member (not teamlead) of a team attached
        // directly to the project - sufficient for checkTeamAccessActivity()
        // per RolePermissionManager::checkTeamAccess() (design's assignee
        // restriction).
        $em = $this->getEntityManager();
        $team = new Team('Board service project team ' . uniqid());
        $team->addUser($candidate);
        $em->persist($team);
        $project->addTeam($team);
        $em->flush();

        $sut = $this->getSut();
        $sut->updateCard($activity, ActivityBoardUpdateDTO::fromArray(['assignedTo' => $candidate->getId()]));

        $em->clear();

        $reloadedActivity = $em->getRepository(Activity::class)->find($activity->getId());
        self::assertNotNull($reloadedActivity);

        /** @var ActivityBoardStateRepository $stateRepository */
        $stateRepository = $em->getRepository(ActivityBoardState::class);
        $state = $stateRepository->findOrCreate($reloadedActivity);

        self::assertNotNull($state->getAssignedTo());
        self::assertSame($candidate->getId(), $state->getAssignedTo()->getId());
    }

    public function testUpdateCardRejectsAssignedToWithoutProjectAccess(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card to protect');
        $candidate = $this->getUserByRole(User::ROLE_TEAMLEAD);

        // the project's customer has a team the candidate is NOT part of -
        // checkTeamAccessCustomer() fails, so checkTeamAccessProject() (and
        // therefore checkTeamAccessActivity()) must reject the candidate.
        $em = $this->getEntityManager();
        $customer = $project->getCustomer();
        self::assertNotNull($customer);
        $team = new Team('Board service foreign team ' . uniqid());
        $em->persist($team);
        $customer->addTeam($team);
        $em->flush();

        $sut = $this->getSut();

        $this->expectException(ValidationException::class);

        $sut->updateCard($activity, ActivityBoardUpdateDTO::fromArray(['assignedTo' => $candidate->getId()]));
    }

    public function testUpdateCardRejectsUnknownAssignedToId(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card with bogus assignee');
        $sut = $this->getSut();

        $this->expectException(ValidationException::class);

        $sut->updateCard($activity, ActivityBoardUpdateDTO::fromArray(['assignedTo' => 999999999]));
    }

    public function testUpdateCardAcceptsTechnicalUserWithProjectAccessDespiteNarrowerActivityTeamScoping(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card with restrictive activity team');
        $candidate = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $em = $this->getEntityManager();

        // candidate has project-level access via a project team ...
        $projectTeam = new Team('Board service technical project team ' . uniqid());
        $projectTeam->addUser($candidate);
        $em->persist($projectTeam);
        $project->addTeam($projectTeam);

        // ... but is deliberately excluded from the activity's own team, which
        // would reject them under the stricter checkTeamAccessActivity() chain.
        $activityTeam = new Team('Board service technical restrictive activity team ' . uniqid());
        $em->persist($activityTeam);
        $activity->addTeam($activityTeam);

        $em->flush();

        $sut = $this->getSut();
        $sut->updateCard($activity, ActivityBoardUpdateDTO::fromArray(['technicalUser' => $candidate->getId()]));

        $em->clear();

        $reloadedActivity = $em->getRepository(Activity::class)->find($activity->getId());
        self::assertNotNull($reloadedActivity);

        /** @var ActivityBoardStateRepository $stateRepository */
        $stateRepository = $em->getRepository(ActivityBoardState::class);
        $state = $stateRepository->findOrCreate($reloadedActivity);

        self::assertNotNull($state->getTechnicalUser());
        self::assertSame($candidate->getId(), $state->getTechnicalUser()->getId());
    }

    public function testUpdateCardAcceptsTechnicalUserWhenCandidateCanSeeAllData(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card for admin bypass');
        $candidate = $this->getUserByRole(User::ROLE_SUPER_ADMIN);

        $em = $this->getEntityManager();

        // the project is restricted to a team the admin candidate is NOT a
        // member of - only canSeeAllData() (checkTeamAccess()'s admin bypass,
        // reached via checkProjectAccessForActivity() -> checkTeamAccessProject())
        // can explain acceptance here.
        $projectTeam = new Team('Board service admin bypass project team ' . uniqid());
        $em->persist($projectTeam);
        $project->addTeam($projectTeam);
        $em->flush();

        $sut = $this->getSut();
        $sut->updateCard($activity, ActivityBoardUpdateDTO::fromArray(['functionalUser' => $candidate->getId()]));

        $em->clear();

        $reloadedActivity = $em->getRepository(Activity::class)->find($activity->getId());
        self::assertNotNull($reloadedActivity);

        /** @var ActivityBoardStateRepository $stateRepository */
        $stateRepository = $em->getRepository(ActivityBoardState::class);
        $state = $stateRepository->findOrCreate($reloadedActivity);

        self::assertNotNull($state->getFunctionalUser());
        self::assertSame($candidate->getId(), $state->getFunctionalUser()->getId());
    }

    public function testUpdateCardStillRejectsAssignedToForSameUserExcludedFromActivityTeam(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card with restrictive activity team for assignedTo');
        $candidate = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $em = $this->getEntityManager();

        $projectTeam = new Team('Board service assignedTo project team ' . uniqid());
        $projectTeam->addUser($candidate);
        $em->persist($projectTeam);
        $project->addTeam($projectTeam);

        $activityTeam = new Team('Board service assignedTo restrictive activity team ' . uniqid());
        $em->persist($activityTeam);
        $activity->addTeam($activityTeam);

        $em->flush();

        $sut = $this->getSut();

        $this->expectException(ValidationException::class);

        // same candidate, same activity, but as assignedTo - must still be
        // rejected exactly as before this change (full checkTeamAccessActivity chain).
        $sut->updateCard($activity, ActivityBoardUpdateDTO::fromArray(['assignedTo' => $candidate->getId()]));
    }

    public function testUpdateCardRejectionMessageForTechnicalUserNamesProjectNotActivity(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card rejecting technical user');
        $candidate = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $em = $this->getEntityManager();
        // restrict the PROJECT itself (not the customer) - project access is
        // checked via the project's own team list only, see
        // checkTeamAccessProjectOnly()
        $team = new Team('Board service foreign team technical ' . uniqid());
        $em->persist($team);
        $project->addTeam($team);
        $em->flush();

        $sut = $this->getSut();

        try {
            $sut->updateCard($activity, ActivityBoardUpdateDTO::fromArray(['technicalUser' => $candidate->getId()]));
            self::fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            self::assertSame('Assignee does not have access to this project', $e->getMessage());
        }
    }

    public function testUpdateCardExplicitNullClearsAssignedTo(): void
    {
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card to unassign');
        $candidate = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $em = $this->getEntityManager();
        $team = new Team('Board service unassign team ' . uniqid());
        $team->addUser($candidate);
        $em->persist($team);
        $project->addTeam($team);
        $em->flush();

        $sut = $this->getSut();
        $sut->updateCard($activity, ActivityBoardUpdateDTO::fromArray(['assignedTo' => $candidate->getId()]));
        $sut->updateCard($activity, ActivityBoardUpdateDTO::fromArray(['assignedTo' => null]));

        $em->clear();

        $reloadedActivity = $em->getRepository(Activity::class)->find($activity->getId());
        self::assertNotNull($reloadedActivity);

        /** @var ActivityBoardStateRepository $stateRepository */
        $stateRepository = $em->getRepository(ActivityBoardState::class);
        $state = $stateRepository->findOrCreate($reloadedActivity);

        self::assertNull($state->getAssignedTo());
    }
}
