<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\Activity;
use App\Entity\ActivityBoardState;
use App\Entity\Customer;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Entity\User;
use App\User\PermissionService;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class ActivityWorkspaceControllerTest extends AbstractControllerBaseTestCase
{
    private function createProject(): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Workspace controller test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Workspace controller test project ' . uniqid());
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

    private function workspaceUrl(Project $project, ?Activity $activity = null): string
    {
        $url = '/admin/project/' . $project->getId() . '/workspace';
        if ($activity !== null) {
            $url .= '/' . $activity->getId();
        }

        return $url;
    }

    /**
     * Grants exactly the given raw permission names to a fresh user via a
     * dedicated Role, mirroring ActivityControllerTest's
     * TEST_CREATE_ACTIVITY_ONLY pattern - so the resulting user has ONLY
     * these permissions, nothing bundled from ROLE_ADMIN/ROLE_TEAMLEAD sets.
     *
     * @param array<int, string> $permissions
     */
    private function createUserWithPermissions(array $permissions): User
    {
        $user = $this->getUserByRole(User::ROLE_USER);

        $em = $this->getEntityManager();

        // RolePermissionManager::hasPermission() uppercases the role before
        // comparing against the permission map key, so the role name itself
        // must already be all-uppercase (uniqid() can contain lowercase hex).
        $role = (new Role())->setName('TEST_WORKSPACE_' . strtoupper(uniqid()));
        $em->persist($role);

        foreach ($permissions as $permission) {
            $rolePermission = (new RolePermission())->setRole($role)->setPermission($permission)->setAllowed(true);
            $em->persist($rolePermission);
        }

        $roleName = $role->getName();
        self::assertNotNull($roleName);
        $user->addRole($roleName);
        $em->persist($user);
        $em->flush();

        // PermissionService caches the full permission table for a day; only
        // its own saveRolePermission() invalidates that cache entry, so a
        // raw EntityManager flush above is invisible to RolePermissionManager
        // until we invalidate it the same way here.
        /** @var PermissionService $permissionService */
        $permissionService = self::getContainer()->get(PermissionService::class);
        foreach ($em->getRepository(RolePermission::class)->findBy(['role' => $role]) as $rolePermission) {
            $permissionService->saveRolePermission($rolePermission);
        }

        return $user;
    }

    public function testIsSecure(): void
    {
        $this->assertUrlIsSecured('/admin/project/1/workspace');
    }

    /**
     * Traces: "Activity from another project is rejected".
     */
    public function testIndexActionRejectsActivityFromAnotherProjectIdor(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $projectA = $this->createProject();
        $projectB = $this->createProject();
        $foreignActivity = $this->createActivity($projectB, 'Foreign activity');

        $this->request($client, $this->workspaceUrl($projectA, $foreignActivity));

        $this->assertRouteNotFound($client);
        self::assertStringNotContainsString($this->nameOf($foreignActivity), (string) $client->getResponse()->getContent());
    }

    /**
     * Traces: "Authorized user sees thread and form" /
     * "Unauthorized user sees neither thread nor form".
     */
    public function testIndexActionShowsCommentThreadOnlyWithCommentsPermission(): void
    {
        $adminClient = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Commentable activity');

        $this->request($adminClient, $this->workspaceUrl($project, $activity));
        self::assertTrue($adminClient->getResponse()->isSuccessful());
        $adminCrawler = $adminClient->getCrawler();
        self::assertCount(1, $adminCrawler->filter('div.card#comments_box'));
        self::assertCount(1, $adminCrawler->filter('form[name=activity_comment_form]'));

        $viewOnlyUser = $this->createUserWithPermissions(['view_project']);
        $viewOnlyUsername = $viewOnlyUser->getUserIdentifier();

        self::ensureKernelShutdown();
        $viewOnlyClient = $this->loginByUsername($viewOnlyUsername);

        $this->request($viewOnlyClient, $this->workspaceUrl($project, $activity));
        self::assertTrue($viewOnlyClient->getResponse()->isSuccessful());
        $viewOnlyCrawler = $viewOnlyClient->getCrawler();
        self::assertCount(0, $viewOnlyCrawler->filter('div.card#comments_box'));
        self::assertCount(0, $viewOnlyCrawler->filter('form[name=activity_comment_form]'));
    }

    /**
     * Traces: "No activity selected renders an empty state".
     */
    public function testIndexActionWithNoActivitySelectedRendersEmptyStatesWithoutError(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $this->createActivity($project, 'Not selected activity');

        $this->request($client, $this->workspaceUrl($project));

        self::assertTrue($client->getResponse()->isSuccessful());
        $crawler = $client->getCrawler();
        self::assertCount(0, $crawler->filter('div.card#comments_box'));
        self::assertStringContainsString('Select an activity to see its detail and comments', (string) $client->getResponse()->getContent());
    }

    /**
     * Traces: "Selected activity renders base fields".
     */
    public function testIndexActionRendersBaseActivityFieldsAndNoBoardState(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Detailed activity');
        $activity->setComment('A base description for the detail panel');
        $activity->setNumber('ACT-042');

        $em = $this->getEntityManager();
        $milestoneName = 'Workspace milestone ' . uniqid();
        $milestone = new Milestone();
        $milestone->setName($milestoneName);
        $milestone->setProject($project);
        $em->persist($milestone);
        $activity->setMilestone($milestone);

        // board state exists but must never surface in panel 2 (A7 / Rule 9)
        $state = new ActivityBoardState();
        $state->setActivity($activity);
        $em->persist($state);
        $em->persist($activity);
        $em->flush();

        $this->request($client, $this->workspaceUrl($project, $activity));
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($this->nameOf($activity), $content);
        self::assertStringContainsString('A base description for the detail panel', $content);
        self::assertStringContainsString('ACT-042', $content);
        self::assertStringContainsString($milestoneName, $content);

        // board-only vocabulary must never leak into this screen
        self::assertStringNotContainsString('activity_board_card', $content);
        self::assertStringNotContainsString('data-status="todo"', $content);
    }

    /**
     * Traces: "Selected activity renders base fields".
     *
     * The base test above never flips visible/billable away from their
     * Activity defaults (both true) nor sets a budget/timeBudget, so those
     * four conditional branches in index.html.twig are otherwise never
     * exercised by any test in this suite. This covers all four.
     */
    public function testIndexActionRendersNonVisibleNonBillableBudgetAndTimeBudgetFields(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Hidden non-billable budgeted activity');
        $activity->setVisible(false);
        $activity->setBillable(false);
        $activity->setBudget(1234.56);
        $activity->setTimeBudget(7200);

        $em = $this->getEntityManager();
        $em->persist($activity);
        $em->flush();

        $this->request($client, $this->workspaceUrl($project, $activity));
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($this->nameOf($activity), $content);

        // not activity.visible / not activity.billable — both render their
        // row label plus a "No" badge (widgets.label_boolean -> label('no', 'default'))
        self::assertStringContainsString('Visible', $content);
        self::assertStringContainsString('Billable', $content);
        $crawler = $client->getCrawler();
        self::assertCount(
            2,
            $crawler->filter('#activity_workspace_detail_box span.badge.bg-default:contains("No")'),
            'Expected exactly 2 "No" badges: one for visible=false, one for billable=false'
        );

        // activity.hasBudget() / activity.hasTimeBudget()
        self::assertStringContainsString('Budget', $content);
        self::assertStringContainsString('Hourly quota', $content);
    }

    /**
     * Traces: "Panel 1 shows only this project's activities".
     */
    public function testIndexActionPanel1ListsOnlyThisProjectsActivities(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $projectP = $this->createProject();
        $projectQ = $this->createProject();

        $p1 = $this->createActivity($projectP, 'P activity one');
        $p2 = $this->createActivity($projectP, 'P activity two');
        $p3 = $this->createActivity($projectP, 'P activity three');
        $q1 = $this->createActivity($projectQ, 'Q activity one');
        $q2 = $this->createActivity($projectQ, 'Q activity two');

        $this->request($client, $this->workspaceUrl($projectP));
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString($this->nameOf($p1), $content);
        self::assertStringContainsString($this->nameOf($p2), $content);
        self::assertStringContainsString($this->nameOf($p3), $content);
        self::assertStringNotContainsString($this->nameOf($q1), $content);
        self::assertStringNotContainsString($this->nameOf($q2), $content);
    }

    /**
     * Traces: "Direct URL to a selected activity renders its detail".
     */
    public function testIndexActionDirectUrlToActivityRendersDetailAndComments(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Bookmarked activity');

        $em = $this->getEntityManager();
        $em->flush();

        $this->request($client, $this->workspaceUrl($project, $activity));

        self::assertTrue($client->getResponse()->isSuccessful());
        $crawler = $client->getCrawler();
        self::assertStringContainsString($this->nameOf($activity), (string) $client->getResponse()->getContent());
        self::assertCount(1, $crawler->filter('div.card#comments_box'));
    }

    /**
     * Traces: "Comment appears after redirect".
     */
    public function testAddCommentActionPersistsAndRedirectsToWorkspace(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $project = $this->createProject();
        $activity = $this->createActivity($project, 'Commented activity');

        $this->request($client, $this->workspaceUrl($project, $activity));
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=activity_comment_form]')->form();
        $client->submit($form, [
            'activity_comment_form' => [
                'message' => 'A workspace comment for this activity',
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl($this->workspaceUrl($project, $activity)));
        $client->followRedirect();

        $node = $client->getCrawler()->filter('div.card#comments_box .card-body');
        self::assertStringContainsString('A workspace comment for this activity', $node->html());
    }

    public function testAddCommentActionRejectsActivityFromAnotherProjectIdor(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $projectA = $this->createProject();
        $projectB = $this->createProject();
        $foreignActivity = $this->createActivity($projectB, 'Foreign comment target');

        $this->request(
            $client,
            $this->workspaceUrl($projectA, $foreignActivity) . '/comment_add',
            'POST',
            ['activity_comment_form' => ['message' => 'should never persist']]
        );

        $this->assertRouteNotFound($client);
    }
}
