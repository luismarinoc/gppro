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
use App\Entity\ActivityMeta;
use App\Entity\ActivityRate;
use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Project;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Entity\Team;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Entity\UserType as UserCategory;
use App\Repository\ActivityBoardStateRepository;
use App\Tests\DataFixtures\ActivityFixtures;
use App\Tests\DataFixtures\CustomerFixtures;
use App\Tests\DataFixtures\ProjectFixtures;
use App\Tests\DataFixtures\TeamFixtures;
use App\Tests\DataFixtures\TimesheetFixtures;
use App\Tests\Mocks\ActivityTestMetaFieldSubscriberMock;
use App\User\PermissionService;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

#[Group('integration')]
class ActivityControllerTest extends AbstractControllerBaseTestCase
{
    public function testIsSecure(): void
    {
        $this->assertUrlIsSecured('/admin/activity/');
    }

    public function testIsSecureForRole(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/activity/');
    }

    public function testIndexAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $this->assertAccessIsGranted($client, '/admin/activity/');
        $this->assertHasDataTable($client);

        $this->assertPageActions($client, [
            'download toolbar-action' => $this->createUrl('/admin/activity/export'),
            'create modal-ajax-form' => $this->createUrl('/admin/activity/create'),
        ]);
    }

    public function testIndexActionAsSuperAdmin(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/activity/');
        $this->assertHasDataTable($client);

        $this->assertPageActions($client, [
            'download toolbar-action' => $this->createUrl('/admin/activity/export'),
            'create modal-ajax-form' => $this->createUrl('/admin/activity/create'),
        ]);
    }

    public function testIndexActionWithSearchTermQuery(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new ActivityFixtures();
        $fixture->setAmount(5);
        $fixture->setCallback(function (Activity $activity): void {
            $activity->setVisible(true);
            $activity->setComment('I am a foobar with tralalalala some more content');
            $activity->setMetaField((new ActivityMeta())->setName('location')->setValue('homeoffice'));
            $activity->setMetaField((new ActivityMeta())->setName('feature')->setValue('timetracking'));
        });
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/activity/');

        $form = $client->getCrawler()->filter('form.searchform')->form();
        $client->submit($form, [
            'searchTerm' => 'feature:timetracking foo',
            'visibility' => 1,
            'size' => 50,
            'customers' => [1],
            'projects' => [1],
            'page' => 1,
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_activity_admin', 5);
    }

    public function testExportIsSecureForRole(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/activity/export');
    }

    public function testExportAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $this->assertAccessIsGranted($client, '/admin/activity/export');
        $this->assertExcelExportResponse($client, 'gppro-activities_');
    }

    public function testExportActionWithSearchTermQuery(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new ActivityFixtures();
        $fixture->setAmount(5);
        $fixture->setCallback(function (Activity $activity): void {
            $activity->setVisible(true);
            $activity->setComment('I am a foobar with tralalalala some more content');
            $activity->setMetaField((new ActivityMeta())->setName('location')->setValue('homeoffice'));
            $activity->setMetaField((new ActivityMeta())->setName('feature')->setValue('timetracking'));
        });
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/activity/');

        $form = $client->getCrawler()->filter('form.searchform')->form();
        $form->getFormNode()->setAttribute('action', $this->createUrl('/admin/activity/export'));
        $client->submit($form, [
            'searchTerm' => 'feature:timetracking foo',
            'visibility' => 1,
            'size' => 50,
            'customers' => [1],
            'projects' => [1],
            'page' => 1,
        ]);

        $this->assertExcelExportResponse($client, 'gppro-activities_');
    }

    public function testDetailsAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        $fixture = new TimesheetFixtures();
        $fixture->setAmount(10);
        $fixture->setActivities($em->getRepository(Activity::class)->findAll());
        $fixture->setUser($this->getUserByRole(User::ROLE_ADMIN));
        $this->importFixture($fixture);

        $project = $em->getRepository(Project::class)->find(1);
        $fixture = new ActivityFixtures();
        $fixture->setAmount(6); // to trigger a second page
        $fixture->setProjects([$project]);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/activity/1/details');

        $this->assertDetailsPage($client);
    }

    public function testDetailsActionShowsEditDenialMessageForNonEditorViewer(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $viewer = $this->getUserByRole(User::ROLE_USER);
        $viewer->setLocale('es');

        $em = $this->getEntityManager();

        $customer = new Customer('Activity denial test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Activity denial test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);

        $activity = new Activity();
        $activity->setName('Activity denied to viewer ' . uniqid());
        $activity->setProject($project);
        $em->persist($activity);

        // view_team_activity without edit_team_activity - the viewer can
        // reach activity_details but must see the denial message (D4/D5).
        // Role name must be all-uppercase - RolePermissionManager::hasPermission()
        // uppercases the role before matching against the permission map key.
        $role = (new Role())->setName('TEST_VIEW_ACTIVITY_DETAILS_ONLY_' . strtoupper(uniqid()));
        $viewActivityPermission = (new RolePermission())->setRole($role)->setPermission('view_team_activity')->setAllowed(true);

        $roleName = $role->getName();
        self::assertNotNull($roleName);
        $viewer->addRole($roleName);

        $team = new Team('Activity denial test team ' . uniqid());
        $team->addUser($viewer);
        $em->persist($team);
        $project->addTeam($team);

        $em->persist($role);
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
        $permissionService->saveRolePermission($viewActivityPermission);

        $activityId = $activity->getId();
        self::assertNotNull($activityId);

        // requestPure() (not request(), which always forces the /en prefix
        // via createUrl()) with the explicit /es/ locale prefix
        // (config/routes.yaml wraps every controller route in /{_locale}) so
        // the denial message is rendered in the exact localized string the
        // spec/design require, independent of the environment's default_locale.
        $this->requestPure($client, '/es/admin/activity/' . $activityId . '/details');
        self::assertTrue($client->getResponse()->isSuccessful());

        $denialMessage = $client->getCrawler()->filter('#activity_edit_denied');
        self::assertCount(1, $denialMessage);
        self::assertSame('No tenés permiso para editar esta actividad', trim($denialMessage->text()));
    }

    public function testDetailsActionHidesEditDenialMessageForEditor(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $this->assertAccessIsGranted($client, '/admin/activity/1/details');

        $denialMessage = $client->getCrawler()->filter('#activity_edit_denied');
        self::assertCount(0, $denialMessage);
    }

    public function testDetailsActionRevenueOnlyCountsInvoicedTimesheets(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        /** @var Project $project */
        $project = $em->getRepository(Project::class)->find(1);
        $customer = $project->getCustomer();
        self::assertNotNull($customer);
        $customer->setCurrency('CLP');
        $em->persist($customer);
        $em->flush();

        $user = $this->getUserByRole(User::ROLE_ADMIN);

        $activity = new Activity();
        $activity->setName('Revenue test activity ' . uniqid());
        $activity->setProject($project);
        $em->persist($activity);
        $em->flush();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->setUser($user);
        $invoice->setInvoiceNumber('INV-' . uniqid());
        $invoice->setFilename('invoice-' . uniqid());
        $invoice->setCreatedAt(new \DateTime());
        $invoice->setCurrency('CLP');
        $invoice->setTotal(2000.0);
        $invoice->setVat(0.0);
        $invoice->setTax(0.0);
        $invoice->setDueDays(30);
        $em->persist($invoice);
        $em->flush();

        // billable but never invoiced -> must NOT count as revenue
        $notInvoiced = new Timesheet();
        $notInvoiced->setProject($project);
        $notInvoiced->setActivity($activity);
        $notInvoiced->setUser($user);
        $notInvoiced->setBegin(new \DateTime('2026-07-01 09:00:00'));
        $notInvoiced->setEnd(new \DateTime('2026-07-01 10:00:00'));
        $notInvoiced->setDuration(3600);
        $notInvoiced->setBillable(true);
        $notInvoiced->setFixedRate(1000.0);
        $em->persist($notInvoiced);

        // billable and actually invoiced -> must count
        $invoiced = new Timesheet();
        $invoiced->setProject($project);
        $invoiced->setActivity($activity);
        $invoiced->setUser($user);
        $invoiced->setBegin(new \DateTime('2026-07-02 09:00:00'));
        $invoiced->setEnd(new \DateTime('2026-07-02 10:00:00'));
        $invoiced->setDuration(3600);
        $invoiced->setBillable(true);
        $invoiced->setFixedRate(2000.0);
        $invoiced->setInvoice($invoice);
        $em->persist($invoiced);
        $em->flush();

        $this->assertAccessIsGranted($client, '/admin/activity/' . $activity->getId() . '/details');

        $row = $client->getCrawler()->filter('div.card#budget_box tr.revenue_row');
        self::assertEquals(1, $row->count());
        $digitsOnly = preg_replace('/\D/', '', $row->filter('td')->eq(1)->text());
        self::assertSame('2000', $digitsOnly);

        $hoursRow = $client->getCrawler()->filter('div.card#budget_box tr.timesheet_total_invoiced_row');
        self::assertEquals(1, $hoursRow->count());
        $hoursRowText = $hoursRow->filter('td')->eq(1)->text();
        self::assertStringContainsString('1:00', $hoursRowText);
        self::assertSame('2000', preg_replace('/\D/', '', str_replace('1:00', '', $hoursRowText)));
    }

    private function assertDetailsPage(HttpKernelBrowser $client)
    {
        self::assertHasProgressbar($client);

        $node = $client->getCrawler()->filter('div.card#activity_details_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#time_budget_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#budget_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#activity_rates_box');
        self::assertEquals(1, $node->count());
    }

    public function testAddRateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/activity/1/rate');
        $form = $client->getCrawler()->filter('form[name=activity_rate_form]')->form();
        $client->submit($form, [
            'activity_rate_form' => [
                'rate' => 123.45,
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/activity/1/details'));
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.card#activity_rates_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#activity_rates_box table.dataTable tbody tr:not(.summary)');
        self::assertEquals(1, $node->count());
        self::assertStringContainsString('123.45', $node->text(null, true));
    }

    public function testEditRateActionDeniesForeignRate(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $project = $this->getEntityManager()->getRepository(Project::class)->find(1);
        self::assertInstanceOf(Project::class, $project);

        $activity = $this->importFixture((new ActivityFixtures(1))->setProjects([$project]))[0];
        $rate = new ActivityRate();
        $rate->setActivity($activity);
        $rate->setRate(123.45);

        $em = $this->getEntityManager();
        $em->persist($rate);
        $em->flush();

        $this->request($client, '/admin/activity/1/rate/' . $rate->getId());

        $this->assertAccessDenied($client);
    }

    public function testCreateWithProjectActionDeniesUserWithoutEditProjectPermission(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $user = $this->getUserByRole(User::ROLE_USER);

        $customer = $this->importFixture(new CustomerFixtures(1))[0];
        $project = $this->importFixture((new ProjectFixtures(1))->setCustomers([$customer]))[0];

        $em = $this->getEntityManager();

        $role = (new Role())->setName('TEST_CREATE_ACTIVITY_ONLY');
        $permission = (new RolePermission())->setRole($role)->setPermission('create_activity')->setAllowed(true);

        $roleName = $role->getName();
        self::assertNotNull($roleName);
        $user->addRole($roleName);

        $em->persist($role);
        $em->persist($permission);
        $em->persist($user);
        $em->flush();

        $this->request($client, '/admin/activity/create/' . $project->getId());

        $this->assertAccessDenied($client);
    }

    public function testCreateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/activity/create');
        $form = $client->getCrawler()->filter('form[name=activity_edit_form]')->form();
        $client->submit($form, [
            'activity_edit_form' => [
                'name' => 'An AcTiVitY Name',
                'project' => '1',
            ]
        ]);

        $location = $this->assertIsModalRedirect($client, '/details');
        $this->requestPure($client, $location);

        $this->assertDetailsPage($client);
        $this->assertHasFlashSuccess($client);

        $activities = $this->getEntityManager()->getRepository(Activity::class)->findAll();
        $activity = array_pop($activities);
        $id = $activity->getId();

        $this->request($client, '/admin/activity/' . $id . '/edit');
        $editForm = $client->getCrawler()->filter('form[name=activity_edit_form]')->form();
        self::assertEquals('An AcTiVitY Name', $editForm->get('activity_edit_form[name]')->getValue());
    }

    public function testCreateActionShowsMetaFields(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        static::assertInstanceOf(EventDispatcher::class, $eventDispatcher);
        $eventDispatcher->addSubscriber(new ActivityTestMetaFieldSubscriberMock());
        $this->assertAccessIsGranted($client, '/admin/activity/create');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=activity_edit_form]')->form();
        self::assertTrue($form->has('activity_edit_form[metaFields][metatestmock][value]'));
        self::assertTrue($form->has('activity_edit_form[metaFields][foobar][value]'));
        self::assertFalse($form->has('activity_edit_form[metaFields][0][value]'));
    }

    public function testEditAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/activity/1/edit');
        $form = $client->getCrawler()->filter('form[name=activity_edit_form]')->form();
        self::assertEquals('Test', $form->get('activity_edit_form[name]')->getValue());
        $client->submit($form, [
            'activity_edit_form' => ['name' => 'Test 2']
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/activity/1/details'));
        $client->followRedirect();
        $this->request($client, '/admin/activity/1/edit');
        $editForm = $client->getCrawler()->filter('form[name=activity_edit_form]')->form();
        self::assertEquals('Test 2', $editForm->get('activity_edit_form[name]')->getValue());
    }

    public function testEditActionForGlobalActivity(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/activity/1/edit');
        $form = $client->getCrawler()->filter('form[name=activity_edit_form]')->form();
        self::assertEquals('Test', $form->get('activity_edit_form[name]')->getValue());
        $client->submit($form, [
            'activity_edit_form' => ['name' => 'Test 2']
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/activity/1/details'));
        $client->followRedirect();
        $this->request($client, '/admin/activity/1/edit');
        $editForm = $client->getCrawler()->filter('form[name=activity_edit_form]')->form();
        self::assertEquals('Test 2', $editForm->get('activity_edit_form[name]')->getValue());
    }

    public function testEditActionSavesTechnicalUserWithProjectAccess(): void
    {
        // zero prior coverage of saveBoardAssignees() (design's Test Impact
        // Map): happy-path save of a project-access technical user through
        // the activity edit form, exercising ActivityController::saveBoardAssignees()
        // -> ActivityBoardService::updateCard() end to end.
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $customer = new Customer('Activity controller test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Activity controller test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);

        $activity = new Activity();
        $activity->setName('Activity for technical user save ' . uniqid());
        $activity->setProject($project);
        $em->persist($activity);
        $em->flush();

        $candidate = $this->getUserByRole(User::ROLE_TEAMLEAD);
        $candidate->setUserType(UserCategory::TECHNICAL);

        $team = new Team('Activity controller technical project team ' . uniqid());
        $team->addUser($candidate);
        $em->persist($team);
        $project->addTeam($team);
        $em->persist($candidate);
        $em->flush();

        $activityId = $activity->getId();
        self::assertNotNull($activityId);
        $candidateId = $candidate->getId();
        self::assertNotNull($candidateId);

        $this->request($client, '/admin/activity/' . $activityId . '/edit');
        self::assertTrue($client->getResponse()->isSuccessful());
        $form = $client->getCrawler()->filter('form[name=activity_edit_form]')->form();
        $client->submit($form, [
            'activity_edit_form' => [
                'technicalUser' => (string) $candidateId,
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/activity/' . $activityId . '/details'));
        $client->followRedirect();
        $this->assertHasFlashSuccess($client);

        $em->clear();
        $reloadedActivity = $em->getRepository(Activity::class)->find($activityId);
        self::assertNotNull($reloadedActivity);

        /** @var ActivityBoardStateRepository $stateRepository */
        $stateRepository = $em->getRepository(ActivityBoardState::class);
        $state = $stateRepository->findOneBy(['activity' => $reloadedActivity]);
        self::assertNotNull($state);
        self::assertNotNull($state->getTechnicalUser());
        self::assertSame($candidateId, $state->getTechnicalUser()->getId());
    }

    public function testTeamPermissionAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        /** @var Activity $activity */
        $activity = $em->getRepository(Activity::class)->findAll()[0];
        self::assertEquals(0, $activity->getTeams()->count());
        $id = $activity->getId();

        $fixture = new TeamFixtures();
        $fixture->setAmount(2);
        $fixture->setAddCustomer(false);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/activity/' . $id . '/permissions');
        $form = $client->getCrawler()->filter('form[name=activity_team_permission_form]')->form();
        /** @var ChoiceFormField $team1 */
        $team1 = $form->get('activity_team_permission_form[teams][0]');
        $team1->tick();
        /** @var ChoiceFormField $team2 */
        $team2 = $form->get('activity_team_permission_form[teams][1]');
        $team2->tick();

        $client->submit($form);
        $this->assertIsRedirect($client, $this->createUrl('/admin/activity/' . $id . '/details'));

        /** @var Activity $activity */
        $activity = $em->getRepository(Activity::class)->find($id);
        self::assertEquals(2, $activity->getTeams()->count());
    }

    public function testDeleteAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->request($client, '/admin/activity/1/edit');
        self::assertTrue($client->getResponse()->isSuccessful());

        $this->request($client, '/admin/activity/1/delete');

        self::assertTrue($client->getResponse()->isSuccessful());
        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/activity/1/delete'), $form->getUri());
        $client->submit($form);

        $client->followRedirect();
        $this->assertHasFlashDeleteSuccess($client);
        $this->assertHasNoEntriesWithFilter($client);

        $this->request($client, '/admin/activity/1/edit');
        self::assertFalse($client->getResponse()->isSuccessful());
    }

    public function testDeleteActionWithTimesheetEntries(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        $fixture = new TimesheetFixtures();
        $fixture->setUser($this->getUserByRole(User::ROLE_USER));
        $fixture->setAmount(10);
        $this->importFixture($fixture);

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(10, \count($timesheets));

        /** @var Timesheet $entry */
        foreach ($timesheets as $entry) {
            self::assertEquals(1, $entry->getActivity()->getId());
        }

        $this->request($client, '/admin/activity/1/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/activity/1/delete'), $form->getUri());
        $client->submit($form);

        $this->assertIsRedirect($client, $this->createUrl('/admin/activity/'));
        $client->followRedirect();
        $this->assertHasFlashDeleteSuccess($client);
        $this->assertHasNoEntriesWithFilter($client);

        $em->clear();
        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(0, \count($timesheets));

        $this->request($client, '/admin/activity/1/edit');
        self::assertFalse($client->getResponse()->isSuccessful());
    }

    public function testDeleteActionWithTimesheetEntriesAndReplacement(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        $fixture = new TimesheetFixtures();
        $fixture->setUser($this->getUserByRole(User::ROLE_USER));
        $fixture->setAmount(10);
        $this->importFixture($fixture);
        $fixture = new ActivityFixtures();
        $fixture->setAmount(1)->setIsGlobal(true)->setIsVisible(true);
        $activities = $this->importFixture($fixture);
        $activity = $activities[0];
        $id = $activity->getId();

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(10, \count($timesheets));

        /** @var Timesheet $entry */
        foreach ($timesheets as $entry) {
            self::assertEquals(1, $entry->getActivity()->getId());
        }

        $this->request($client, '/admin/activity/1/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/activity/1/delete'), $form->getUri());
        $client->submit($form, [
            'form' => [
                'activity' => $id
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/activity/'));
        $client->followRedirect();
        $this->assertHasDataTable($client);
        $this->assertHasFlashSuccess($client);

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(10, \count($timesheets));

        /** @var Timesheet $entry */
        foreach ($timesheets as $entry) {
            self::assertEquals($id, $entry->getActivity()->getId());
        }

        $this->request($client, '/admin/activity/1/edit');
        self::assertFalse($client->getResponse()->isSuccessful());
    }

    #[DataProvider('getValidationTestData')]
    public function testValidationForCreateAction(array $formData, array $validationFields): void
    {
        $this->assertFormHasValidationError(
            User::ROLE_ADMIN,
            '/admin/activity/create',
            'form[name=activity_edit_form]',
            $formData,
            $validationFields
        );
    }

    public static function getValidationTestData()
    {
        return [
            [
                [
                    'activity_edit_form' => [
                        'name' => '',
                        'project' => 0,
                    ]
                ],
                [
                    '#activity_edit_form_name',
                    '#activity_edit_form_project',
                ]
            ],
        ];
    }
}
