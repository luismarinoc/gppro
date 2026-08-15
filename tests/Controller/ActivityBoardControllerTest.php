<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Activity\ActivityStatisticService;
use App\Entity\Activity;
use App\Entity\ActivityBoardPriority;
use App\Entity\ActivityBoardState;
use App\Entity\ActivityBoardStatus;
use App\Entity\ActivityRate;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Entity\Team;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\ActivityBoardStateRepository;
use App\User\PermissionService;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

#[Group('integration')]
class ActivityBoardControllerTest extends AbstractControllerBaseTestCase
{
    private function createProject(): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Board controller test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Board controller test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);

        $em->flush();

        return $project;
    }

    private function createActivity(Project $project, string $name): Activity
    {
        $em = $this->getEntityManager();

        $activity = new Activity();
        $activity->setName($name . ' ' . uniqid());
        $activity->setProject($project);
        $em->persist($activity);
        $em->flush();

        return $activity;
    }

    private function nameOf(Activity $activity): string
    {
        $name = $activity->getName();
        self::assertNotNull($name);

        return $name;
    }

    public function testIsSecure(): void
    {
        // no entity manager access before this call: assertUrlIsSecured()
        // boots its own kernel/client and a second boot is not supported
        // (same constraint as MilestoneInvoiceControllerTest::testIsSecure())
        $this->assertUrlIsSecured('/admin/project/1/board');
    }

    public function testIsSecureForRole(): void
    {
        // ROLE_USER has neither the full nor the team-scoped 'view_project'
        // permission (see config/packages/gppro.yaml), so it is denied
        // before a Project with id=1 needs to actually exist.
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/project/1/board');
    }

    public function testBoardActionRendersFourColumnsWithEmptyState(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $crawler = $client->getCrawler();
        $columns = $crawler->filter('.activity_board_column');
        self::assertCount(4, $columns);

        $statuses = $columns->each(static fn (Crawler $node): string => $node->attr('data-status') ?? '');
        self::assertSame(['todo', 'in_progress', 'in_review', 'done'], $statuses);

        $emptyStateNodes = $crawler->filter('.activity_board_column_empty');
        self::assertCount(4, $emptyStateNodes, 'a project with no activities must show the empty state in all 4 columns');
        self::assertSame('No activities in this column.', trim($emptyStateNodes->first()->text()));
    }

    public function testBoardActionPlacesStatelessActivityInTodoColumnAsUnassigned(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Stateless activity');

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $crawler = $client->getCrawler();
        $todoColumn = $crawler->filter('.activity_board_column[data-status="todo"]');
        self::assertCount(1, $todoColumn);
        $todoText = $todoColumn->text();

        self::assertStringContainsString($this->nameOf($activity), $todoText);
        self::assertStringContainsString('Unassigned', $todoText);

        $otherColumns = $crawler->filter('.activity_board_column[data-status="in_progress"], .activity_board_column[data-status="in_review"], .activity_board_column[data-status="done"]');
        self::assertCount(3, $otherColumns);
        $otherColumns->each(function (Crawler $node) use ($activity): void {
            self::assertStringNotContainsString($this->nameOf($activity), $node->text());
        });
    }

    public function testBoardActionShowsCardNamePriorityDueDateAndAssignee(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card with full state');
        $assignee = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $dueDate = new \DateTime('2026-08-01');

        $em = $this->getEntityManager();
        $state = new ActivityBoardState();
        $state->setActivity($activity);
        $state->setPriority(ActivityBoardPriority::URGENT);
        $state->setDueDate($dueDate);
        $state->setAssignedTo($assignee);
        $em->persist($state);
        $em->flush();

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $crawler = $client->getCrawler();
        $card = $crawler->filter('.activity_board_card');
        self::assertCount(1, $card, 'exactly one card must be rendered for the single activity');
        $cardText = $card->text();

        self::assertStringContainsString($this->nameOf($activity), $cardText);
        self::assertStringContainsString('Urgent', $cardText);
        self::assertStringContainsString($this->formatDate($dueDate), $cardText);
        self::assertStringContainsString($assignee->getDisplayName(), $cardText);
        self::assertStringContainsString(mb_strtolower($assignee->getDisplayName()), (string) $card->attr('data-search'));
        self::assertStringNotContainsString('Unassigned', $cardText);
    }

    public function testBoardActionPreservesRoleUsersAndShowsUnassignedOnlyForEmptyUserSet(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $assignedOnly = $this->createActivity($project, 'Assigned-only card');
        $technicalOnly = $this->createActivity($project, 'Technical-only card');
        $allUsers = $this->createActivity($project, 'All-users card');
        $noUsers = $this->createActivity($project, 'No-users card');
        $assignedUser = $this->getUserByRole(User::ROLE_TEAMLEAD);
        $technicalUser = $this->getUserByRole(User::ROLE_USER);
        $functionalUser = $this->getUserByRole(User::ROLE_ADMIN);

        $em = $this->getEntityManager();
        $assignedState = (new ActivityBoardState())->setActivity($assignedOnly)->setAssignedTo($assignedUser);
        $technicalState = (new ActivityBoardState())->setActivity($technicalOnly)->setTechnicalUser($technicalUser);
        $allUsersState = (new ActivityBoardState())
            ->setActivity($allUsers)
            ->setAssignedTo($assignedUser)
            ->setTechnicalUser($technicalUser)
            ->setFunctionalUser($functionalUser);
        $noUsersState = (new ActivityBoardState())->setActivity($noUsers);
        $em->persist($assignedState);
        $em->persist($technicalState);
        $em->persist($allUsersState);
        $em->persist($noUsersState);
        $em->flush();

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $cards = $client->getCrawler()->filter('.activity_board_card');
        self::assertCount(4, $cards);

        $assignedOnlyCard = $cards->filter('[data-activity-id="' . $assignedOnly->getId() . '"]');
        self::assertCount(1, $assignedOnlyCard);
        self::assertStringContainsString($assignedUser->getDisplayName(), $assignedOnlyCard->text());
        self::assertStringNotContainsString('Unassigned', $assignedOnlyCard->text());

        $technicalOnlyCard = $cards->filter('[data-activity-id="' . $technicalOnly->getId() . '"]');
        self::assertCount(1, $technicalOnlyCard);
        self::assertStringContainsString($technicalUser->getDisplayName(), $technicalOnlyCard->html());
        self::assertStringNotContainsString('Unassigned', $technicalOnlyCard->text());

        $allUsersCard = $cards->filter('[data-activity-id="' . $allUsers->getId() . '"]');
        self::assertCount(1, $allUsersCard);
        self::assertStringContainsString($assignedUser->getDisplayName(), $allUsersCard->text());
        self::assertStringContainsString($technicalUser->getDisplayName(), $allUsersCard->html());
        self::assertStringContainsString($functionalUser->getDisplayName(), $allUsersCard->html());
        self::assertCount(2, $allUsersCard->filter('.activity_board_card_roles [title]'));
        self::assertStringNotContainsString('Unassigned', $allUsersCard->text());

        $noUsersCard = $cards->filter('[data-activity-id="' . $noUsers->getId() . '"]');
        self::assertCount(1, $noUsersCard);
        self::assertStringContainsString('Unassigned', $noUsersCard->text());
    }

    public function testBoardActionEscapesAssignedDisplayNameInCardAndSearchMetadata(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Escaped assigned user');
        $assignee = $this->getUserByRole(User::ROLE_TEAMLEAD);
        $assignee->setAlias('<Alex & Co>');

        $em = $this->getEntityManager();
        $state = (new ActivityBoardState())->setActivity($activity)->setAssignedTo($assignee);
        $em->persist($state);
        $em->flush();

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $card = $client->getCrawler()->filter('.activity_board_card')->first();
        self::assertStringContainsString('<Alex & Co>', $card->text());
        self::assertStringContainsString('<alex & co>', (string) $card->attr('data-search'));
        self::assertStringContainsString('&lt;Alex &amp; Co&gt;', (string) $card->html());
    }

    public function testBoardActionCardsAlwaysShowEditIconLinkingToDetails(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activityOne = $this->createActivity($project, 'Card with edit icon one');
        $activityTwo = $this->createActivity($project, 'Card with edit icon two');

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $crawler = $client->getCrawler();
        $cards = $crawler->filter('.activity_board_card');
        self::assertCount(2, $cards);

        $editIcons = $crawler->filter('.activity_board_card a.activity_board_card_edit');
        self::assertCount(2, $editIcons, 'the edit icon must always be present, never hover-gated');

        $hrefs = $editIcons->each(static fn (Crawler $node): string => (string) $node->attr('href'));
        foreach ([$activityOne, $activityTwo] as $activity) {
            $expected = '/admin/activity/' . $activity->getId() . '/details';
            $matches = array_filter($hrefs, static fn (string $href): bool => str_ends_with($href, $expected));
            self::assertNotEmpty($matches, 'expected an href ending with "' . $expected . '" among: ' . implode(', ', $hrefs));
        }
    }

    public function testBoardActionExposesCanEditFlagTrueForFullyPermittedUser(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card for admin can-edit flag');

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $card = $client->getCrawler()->filter('.activity_board_card[data-activity-id="' . $activity->getId() . '"]');
        self::assertCount(1, $card);
        self::assertSame('1', $card->attr('data-can-edit'));
    }

    public function testBoardActionExposesCanEditFlagFalseForViewOnlyTeamScopedUser(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $viewer = $this->getUserByRole(User::ROLE_USER);

        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card for view-only can-edit flag');

        $em = $this->getEntityManager();

        // view_team_project/view_team_activity without edit_team_activity -
        // the viewer can see the board and the card, but must never be
        // marked as able to edit it (data-can-edit="0"). Role name must be
        // all-uppercase - RolePermissionManager::hasPermission() uppercases
        // the role before matching against the permission map key.
        $role = (new Role())->setName('TEST_VIEW_TEAM_ONLY_' . strtoupper(uniqid()));
        $viewProjectPermission = (new RolePermission())->setRole($role)->setPermission('view_team_project')->setAllowed(true);
        $viewActivityPermission = (new RolePermission())->setRole($role)->setPermission('view_team_activity')->setAllowed(true);

        $roleName = $role->getName();
        self::assertNotNull($roleName);
        $viewer->addRole($roleName);

        $team = new Team('Board controller view-only team ' . uniqid());
        $team->addUser($viewer);
        $em->persist($team);
        $project->addTeam($team);

        $em->persist($role);
        $em->persist($viewProjectPermission);
        $em->persist($viewActivityPermission);
        $em->persist($viewer);
        $em->flush();

        // PermissionService caches the full permission table for a day; only
        // its own saveRolePermission() invalidates that cache entry, so a
        // raw EntityManager flush above is invisible to RolePermissionManager
        // until it is invalidated the same way here (mirrors
        // ActivityWorkspaceControllerTest::createUserWithPermissions()).
        /** @var PermissionService $permissionService */
        $permissionService = self::getContainer()->get(PermissionService::class);
        foreach ([$viewProjectPermission, $viewActivityPermission] as $rolePermission) {
            $permissionService->saveRolePermission($rolePermission);
        }

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $card = $client->getCrawler()->filter('.activity_board_card[data-activity-id="' . $activity->getId() . '"]');
        self::assertCount(1, $card);
        self::assertSame('0', $card->attr('data-can-edit'));
    }

    public function testBoardActionExposesDetailsUrlTemplateAlongsideEditUrlTemplate(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();

        $this->request($client, '/admin/project/' . $project->getId() . '/board');
        self::assertTrue($client->getResponse()->isSuccessful());

        $box = $client->getCrawler()->filter('#activity_board_box');
        self::assertCount(1, $box);

        $detailsUrl = (string) $box->attr('data-details-url');
        self::assertStringContainsString('/details', $detailsUrl);

        $editUrl = (string) $box->attr('data-edit-url');
        self::assertNotSame('', $editUrl, 'data-edit-url must remain present alongside the new data-details-url');
    }

    private function updateCardUrl(Project $project, Activity $activity): string
    {
        return '/admin/project/' . $project->getId() . '/board/' . $activity->getId();
    }

    private function reloadState(Activity $activity): ?ActivityBoardState
    {
        $em = $this->getEntityManager();
        $em->clear();

        $reloadedActivity = $em->getRepository(Activity::class)->find($activity->getId());
        self::assertNotNull($reloadedActivity);

        /** @var ActivityBoardStateRepository $stateRepository */
        $stateRepository = $em->getRepository(ActivityBoardState::class);

        return $stateRepository->findOneBy(['activity' => $reloadedActivity]);
    }

    public function testUpdateCardActionPersistsStatusChangeForAuthorizedUser(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card to move');

        $this->request(
            $client,
            $this->updateCardUrl($project, $activity),
            'PATCH',
            [],
            json_encode(['status' => 'in_progress'], \JSON_THROW_ON_ERROR)
        );

        self::assertTrue($client->getResponse()->isSuccessful());
        $responseData = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($responseData);
        self::assertSame('in_progress', $responseData['status']);

        $state = $this->reloadState($activity);
        self::assertNotNull($state);
        self::assertSame(ActivityBoardStatus::IN_PROGRESS, $state->getStatus());
    }

    public function testUpdateCardActionRequiresEditActivityPermission(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card protected by permission');

        $this->request(
            $client,
            $this->updateCardUrl($project, $activity),
            'PATCH',
            [],
            json_encode(['status' => 'in_progress'], \JSON_THROW_ON_ERROR)
        );

        self::assertFalse($client->getResponse()->isSuccessful());
        $this->assertAccessDenied($client);

        self::assertNull($this->reloadState($activity));
    }

    public function testUpdateCardActionRejectsInvalidStatus(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card with bad status');

        $this->request(
            $client,
            $this->updateCardUrl($project, $activity),
            'PATCH',
            [],
            json_encode(['status' => 'not-a-real-status'], \JSON_THROW_ON_ERROR)
        );

        self::assertSame(400, $client->getResponse()->getStatusCode());
        self::assertNull($this->reloadState($activity));
    }

    public function testUpdateCardActionRejectsActivityFromAnotherProjectIdor(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $projectA = $this->createProject();
        $projectB = $this->createProject();
        // Activity belongs to project B; the attacker addresses it through
        // project A's id in the URL - {id} and {activity} are both plain,
        // trivially enumerable auto-increment integers.
        $foreignActivity = $this->createActivity($projectB, 'Foreign activity');

        $this->request(
            $client,
            $this->updateCardUrl($projectA, $foreignActivity),
            'PATCH',
            [],
            json_encode(['status' => 'in_progress'], \JSON_THROW_ON_ERROR)
        );

        $this->assertRouteNotFound($client);
        self::assertNull($this->reloadState($foreignActivity));
    }

    public function testUpdateCardActionRejectsAssigneeWithoutProjectAccess(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card with restricted assignee');
        $candidate = $this->getUserByRole(User::ROLE_TEAMLEAD);

        // the project's customer has a team the candidate is NOT part of -
        // checkTeamAccessActivity() must reject the candidate (same pattern
        // as MilestoneInvoiceControllerTest::testCreateActionRejectsForeignCustomerMilestoneOutsideTeamAccessIdor()).
        $em = $this->getEntityManager();
        $customer = $project->getCustomer();
        self::assertNotNull($customer);
        $team = new Team('Board controller foreign team ' . uniqid());
        $em->persist($team);
        $customer->addTeam($team);
        $em->flush();

        $this->request(
            $client,
            $this->updateCardUrl($project, $activity),
            'PATCH',
            [],
            json_encode(['assignedTo' => $candidate->getId()], \JSON_THROW_ON_ERROR)
        );

        self::assertSame(400, $client->getResponse()->getStatusCode());

        $state = $this->reloadState($activity);
        self::assertTrue(null === $state || null === $state->getAssignedTo());
    }

    public function testUpdateCardActionAcceptsAssigneeWithProjectAccess(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Card with valid assignee');
        $candidate = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $em = $this->getEntityManager();
        $team = new Team('Board controller project team ' . uniqid());
        $team->addUser($candidate);
        $em->persist($team);
        $project->addTeam($team);
        $em->flush();

        $this->request(
            $client,
            $this->updateCardUrl($project, $activity),
            'PATCH',
            [],
            json_encode(['assignedTo' => $candidate->getId()], \JSON_THROW_ON_ERROR)
        );

        self::assertTrue($client->getResponse()->isSuccessful());

        $state = $this->reloadState($activity);
        self::assertNotNull($state);
        self::assertNotNull($state->getAssignedTo());
        self::assertSame($candidate->getId(), $state->getAssignedTo()->getId());
    }

    /**
     * The hard constraint of this feature (design's central invariant):
     * moving a kanban card must NEVER touch billing/timesheet data. Captures
     * the raw rows of every table a "normal" activity write could plausibly
     * reach - gppro_activities, gppro_timesheet, gppro_activities_rates -
     * plus ActivityStatisticService's computed output, before and after a
     * PATCH, and asserts byte-identical results.
     */
    public function testStatusChangeDoesNotTouchBillingData(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Billable card');

        $em = $this->getEntityManager();

        $timesheet = new Timesheet();
        $timesheet->setProject($project);
        $timesheet->setActivity($activity);
        $timesheet->setUser($this->getUserByRole(User::ROLE_ADMIN));
        $timesheet->setBegin(new \DateTime('-2 hour'));
        $timesheet->setEnd(new \DateTime('-1 hour'));
        $timesheet->setDuration(3600);
        $timesheet->setBillable(true);
        $em->persist($timesheet);

        $rate = new ActivityRate();
        $rate->setActivity($activity);
        $rate->setRate(50.0);
        $em->persist($rate);

        $em->flush();

        $activityId = $activity->getId();
        self::assertNotNull($activityId);

        /** @var ActivityStatisticService $statisticService */
        $statisticService = self::getContainer()->get(ActivityStatisticService::class);

        $before = $this->captureBillingSnapshot($activityId, $activity, $statisticService);

        $this->request(
            $client,
            $this->updateCardUrl($project, $activity),
            'PATCH',
            [],
            json_encode(['status' => 'in_review', 'priority' => 'urgent', 'dueDate' => '2026-09-01'], \JSON_THROW_ON_ERROR)
        );
        self::assertTrue($client->getResponse()->isSuccessful());

        // reload the activity through a fresh entity manager state before
        // re-computing statistics/rows, mirroring what a real subsequent
        // request would see.
        $em->clear();
        /** @var Activity $reloadedActivity */
        $reloadedActivity = $em->getRepository(Activity::class)->find($activityId);

        $after = $this->captureBillingSnapshot($activityId, $reloadedActivity, $statisticService);

        self::assertSame($before, $after, 'A board card update must never change gppro_activities/gppro_timesheet/gppro_activities_rates rows or activity statistics.');

        // the board state change itself did persist - proves this is a real
        // negative assertion, not a vacuous "nothing happened at all" test
        $state = $this->reloadState($activity);
        self::assertNotNull($state);
        self::assertSame(ActivityBoardStatus::IN_REVIEW, $state->getStatus());
    }

    /**
     * @return array{activity: array<int, array<string, mixed>>, timesheet: array<int, array<string, mixed>>, rates: array<int, array<string, mixed>>, statistics: array<string, mixed>}
     */
    private function captureBillingSnapshot(int $activityId, Activity $activity, ActivityStatisticService $statisticService): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $activityRows = $connection->fetchAllAssociative('SELECT * FROM gppro_activities WHERE id = ?', [$activityId]);
        $timesheetRows = $connection->fetchAllAssociative('SELECT * FROM gppro_timesheet WHERE activity_id = ? ORDER BY id', [$activityId]);
        $rateRows = $connection->fetchAllAssociative('SELECT * FROM gppro_activities_rates WHERE activity_id = ? ORDER BY id', [$activityId]);

        $statistic = $statisticService->getActivityStatistics($activity);

        return [
            'activity' => $activityRows,
            'timesheet' => $timesheetRows,
            'rates' => $rateRows,
            'statistics' => [
                'duration' => $statistic->getDuration(),
                'rate' => $statistic->getRate(),
                'internalRate' => $statistic->getInternalRate(),
                'counter' => $statistic->getCounter(),
                'durationBillable' => $statistic->getDurationBillable(),
                'rateBillable' => $statistic->getRateBillable(),
            ],
        ];
    }
}
