<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\Activity;
use App\Entity\ActivityMeta;
use App\Entity\ActivityRate;
use App\Entity\FxRate;
use App\Entity\Invoice;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\ProjectMeta;
use App\Entity\ProjectRate;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Entity\Team;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Tests\DataFixtures\ActivityFixtures;
use App\Tests\DataFixtures\CustomerFixtures;
use App\Tests\DataFixtures\ProjectFixtures;
use App\Tests\DataFixtures\TeamFixtures;
use App\Tests\DataFixtures\TimesheetFixtures;
use App\Tests\Mocks\ProjectTestMetaFieldSubscriberMock;
use App\User\PermissionService;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

#[Group('integration')]
class ProjectControllerTest extends AbstractControllerBaseTestCase
{
    public function testIsSecure(): void
    {
        $this->assertUrlIsSecured('/admin/project/');
    }

    public function testIsSecureForRole(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/project/');
    }

    public function testIndexAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $this->assertAccessIsGranted($client, '/admin/project/');
        $this->assertHasDataTable($client);

        $this->assertPageActions($client, [
            'download toolbar-action' => $this->createUrl('/admin/project/export'),
        ]);
    }

    public function testIndexActionAsSuperAdmin(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/project/');
        $this->assertHasDataTable($client);

        $this->assertPageActions($client, [
            'download toolbar-action' => $this->createUrl('/admin/project/export'),
            'create modal-ajax-form' => $this->createUrl('/admin/project/create'),
        ]);
    }

    public function testIndexActionWithSearchTermQuery(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new ProjectFixtures();
        $fixture->setAmount(5);
        $i = 0;
        $fixture->setCallback(function (Project $project) use (&$i): void {
            $project->setVisible(true);
            switch ($i++) {
                case 0:
                    $project->setComment('I am a foo');
                    break;
                case 1:
                    $project->setComment('I am a foo with tralalalala some more content');
                    break;
                case 2:
                    $project->setComment('I am a barfoo with tralalalala some more content');
                    break;
                case 3:
                    $project->setName($project->getName() . ' with');
                    $project->setComment('I am a foobar tralalalala some more content');
                    break;
                default:
                    $project->setComment('I am a foobar with tralalalala some more content');
                    break;
            }
            $project->setMetaField((new ProjectMeta())->setName('location')->setValue('homeoffice'));
            $project->setMetaField((new ProjectMeta())->setName('feature')->setValue('timetracking'));
        });
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/project/');

        $this->assertPageActions($client, [
            'download toolbar-action' => $this->createUrl('/admin/project/export'),
            'create modal-ajax-form' => $this->createUrl('/admin/project/create'),
        ]);

        $form = $client->getCrawler()->filter('form.searchform')->form();
        $client->submit($form, [
            'searchTerm' => 'feature:timetracking foo with',
            'visibility' => 1,
            'customers' => [1],
            'size' => 50,
            'page' => 1,
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_project_admin', 4);
    }

    public function testExportIsSecureForRole(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/project/export');
    }

    public function testExportAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $this->assertAccessIsGranted($client, '/admin/project/export');
        $this->assertExcelExportResponse($client, 'gppro-projects_');
    }

    public function testExportActionWithSearchTermQuery(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new ProjectFixtures();
        $fixture->setAmount(5);
        $fixture->setCallback(function (Project $project): void {
            $project->setVisible(true);
            $project->setComment('I am a foobar with tralalalala some more content');
            $project->setMetaField((new ProjectMeta())->setName('location')->setValue('homeoffice'));
            $project->setMetaField((new ProjectMeta())->setName('feature')->setValue('timetracking'));
        });
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/project/');

        $form = $client->getCrawler()->filter('form.searchform')->form();
        $form->getFormNode()->setAttribute('action', $this->createUrl('/admin/project/export'));
        $client->submit($form, [
            'searchTerm' => 'feature:timetracking foo',
            'visibility' => 1,
            'customers' => [1],
            'size' => 50,
            'page' => 1,
        ]);

        $this->assertExcelExportResponse($client, 'gppro-projects_');
    }

    public function testDetailsAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        $project = $em->getRepository(Project::class)->find(1);

        $fixture = new TimesheetFixtures();
        $fixture->setAmount(10);
        $fixture->setProjects([$project]);
        $fixture->setUser($this->getUserByRole(User::ROLE_ADMIN));
        $this->importFixture($fixture);

        $project = $em->getRepository(Project::class)->find(1);
        $fixture = new ActivityFixtures();
        $fixture->setAmount(6); // to trigger a second page
        $fixture->setProjects([$project]);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/project/1/details');
        $this->assertDetailsPage($client);
    }

    private function assertDetailsPage(HttpKernelBrowser $client)
    {
        self::assertHasProgressbar($client);

        $node = $client->getCrawler()->filter('div.card#project_details_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#activity_list_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#time_budget_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#budget_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#team_listing_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#comments_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#team_listing_box .card-actions a.btn');
        self::assertEquals(2, $node->count());
        $node = $client->getCrawler()->filter('div.card#project_rates_box');
        self::assertEquals(1, $node->count());
    }

    public function testAddRateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAddRate($client, 123.45, 1);
    }

    public function testEditRateActionDeniesForeignRate(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $project = $this->importFixture(new ProjectFixtures(1))[0];
        $rate = new ProjectRate();
        $rate->setProject($project);
        $rate->setRate(123.45);

        $em = $this->getEntityManager();
        $em->persist($rate);
        $em->flush();

        $this->request($client, '/admin/project/1/rate/' . $rate->getId());

        $this->assertAccessDenied($client);
    }

    public function assertAddRate(HttpKernelBrowser $client, $rate, $projectId): void
    {
        $this->assertAccessIsGranted($client, '/admin/project/' . $projectId . '/rate');
        $form = $client->getCrawler()->filter('form[name=project_rate_form]')->form();
        $client->submit($form, [
            'project_rate_form' => [
                'rate' => $rate,
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/project/' . $projectId . '/details'));
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.card#project_rates_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#project_rates_box table.dataTable tbody tr:not(.summary)');
        self::assertEquals(1, $node->count());
        self::assertStringContainsString($rate, $node->text(null, true));
    }

    public function testDuplicateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();
        $project = $em->find(Project::class, 1);
        $project->setMetaField((new ProjectMeta())->setName('foo')->setValue('bar'));
        $project->setEnd(new \DateTime());
        $em->persist($project);
        $team = new Team('project 1');
        $team->addTeamlead($this->getUserByRole(User::ROLE_ADMIN));
        $team->addProject($project);
        $em->persist($team);
        $rate = new ProjectRate();
        $rate->setProject($project);
        $rate->setRate(123.45);
        $em->persist($rate);
        $activity = new Activity();
        $activity->setName('blub');
        $activity->setProject($project);
        $activity->setMetaField((new ActivityMeta())->setName('blub')->setValue('blab'));
        $em->persist($activity);
        $rate = new ActivityRate();
        $rate->setActivity($activity);
        $rate->setRate(123.45);
        $em->persist($rate);
        $em->flush();

        $token = $this->getCsrfToken($client, 'project.duplicate');

        $this->request($client, '/admin/project/1/duplicate/' . $token);
        $this->assertIsRedirect($client, '/details');
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.card#project_rates_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#project_rates_box table.dataTable tbody tr:not(.summary)');
        self::assertEquals(1, $node->count());
        self::assertStringContainsString('123.45', $node->text(null, true));
    }

    public function testDuplicateActionWithInvalidCsrf(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();
        $project = $em->find(Project::class, 1);
        $project->setMetaField((new ProjectMeta())->setName('foo')->setValue('bar'));
        $project->setEnd(new \DateTime());
        $em->persist($project);
        $activity = new Activity();
        $activity->setName('blub');
        $activity->setProject($project);
        $activity->setMetaField((new ActivityMeta())->setName('blub')->setValue('blab'));
        $em->persist($activity);
        $em->flush();

        $this->assertInvalidCsrfToken($client, '/admin/project/1/duplicate/rsetdzfukgli78t6r5uedtjfzkugl', $this->createUrl('/admin/project/1/details'));
    }

    public function testAddCommentAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/project/1/details');
        $form = $client->getCrawler()->filter('form[name=project_comment_form]')->form();
        $client->submit($form, [
            'project_comment_form' => [
                'message' => 'A beautiful and long comment **with some** markdown formatting',
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/project/1/details'));
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.card#comments_box .card-body');
        self::assertStringContainsString('A beautiful and long comment **with some** markdown formatting', $node->html());

        $this->setSystemConfiguration('timesheet.markdown_content', true);
        $this->assertAccessIsGranted($client, '/admin/project/1/details');
        $node = $client->getCrawler()->filter('div.card#comments_box .direct-chat-text');
        self::assertStringContainsString('<p>A beautiful and long comment <strong>with some</strong> markdown formatting</p>', $node->html());
    }

    public function testActivitiesAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/project/1/activities/1');
        $node = $client->getCrawler()->filter('div.card#activity_list_box .card-actions ul.pagination li');
        self::assertEquals(0, $node->count());
        $node = $client->getCrawler()->filter('div.card#activity_list_box .card-actions a.modal-ajax-form.open-edit');
        self::assertEquals(1, $node->count());

        /** @var EntityManager $em */
        $em = $this->getEntityManager();
        $project = $em->getRepository(Project::class)->find(1);
        $fixture = new ActivityFixtures();
        $fixture->setAmount(9); // to trigger a second page (every third activity is hidden)
        $fixture->setProjects([$project]);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/project/1/activities/1');

        $node = $client->getCrawler()->filter('div.card#activity_list_box .card-footer ul.pagination li');
        self::assertEquals(4, $node->count());

        $node = $client->getCrawler()->filter('div.card#activity_list_box .card-body table tbody tr');
        self::assertEquals(5, $node->count());
    }

    public function testCreateWithCustomerActionDeniesUserWithoutEditCustomerPermission(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $user = $this->getUserByRole(User::ROLE_USER);

        $customer = $this->importFixture(new CustomerFixtures(1))[0];

        $em = $this->getEntityManager();

        $role = (new Role())->setName('TEST_CREATE_PROJECT_ONLY');
        $permission = (new RolePermission())->setRole($role)->setPermission('create_project')->setAllowed(true);

        $roleName = $role->getName();
        self::assertNotNull($roleName);
        $user->addRole($roleName);

        $em->persist($role);
        $em->persist($permission);
        $em->persist($user);
        $em->flush();

        $this->request($client, '/admin/project/create/' . $customer->getId());

        $this->assertAccessDenied($client);
    }

    public function testCreateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/project/create');
        $form = $client->getCrawler()->filter('form[name=project_edit_form]')->form();
        $client->submit($form, [
            'project_edit_form' => [
                'name' => 'Test 2',
                'customer' => 1,
            ]
        ]);

        $location = $this->assertIsModalRedirect($client, '/details');
        $this->requestPure($client, $location);

        $this->assertDetailsPage($client);
        $this->assertHasFlashSuccess($client);
    }

    public function testCreateActionShowsMetaFields(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EventDispatcher $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $dispatcher->addSubscriber(new ProjectTestMetaFieldSubscriberMock());
        $this->assertAccessIsGranted($client, '/admin/project/create');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=project_edit_form]')->form();
        self::assertTrue($form->has('project_edit_form[metaFields][metatestmock][value]'));
        self::assertTrue($form->has('project_edit_form[metaFields][foobar][value]'));
        self::assertFalse($form->has('project_edit_form[metaFields][0][value]'));
    }

    public function testEditAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/project/1/edit');
        $form = $client->getCrawler()->filter('form[name=project_edit_form]')->form();
        self::assertEquals('Test', $form->get('project_edit_form[name]')->getValue());
        $client->submit($form, [
            'project_edit_form' => ['name' => 'Test 2']
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/project/1/details'));
        $client->followRedirect();
        $this->request($client, '/admin/project/1/edit');
        $editForm = $client->getCrawler()->filter('form[name=project_edit_form]')->form();
        self::assertEquals('Test 2', $editForm->get('project_edit_form[name]')->getValue());
    }

    public function testTeamPermissionAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        /** @var Project $project */
        $project = $em->getRepository(Project::class)->find(1);
        self::assertEquals(0, $project->getTeams()->count());

        $fixture = new TeamFixtures();
        $fixture->setAmount(2);
        $fixture->setAddCustomer(false);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/project/1/permissions');
        $form = $client->getCrawler()->filter('form[name=project_team_permission_form]')->form();
        /** @var ChoiceFormField $team1 */
        $team1 = $form->get('project_team_permission_form[teams][0]');
        $team1->tick();
        /** @var ChoiceFormField $team2 */
        $team2 = $form->get('project_team_permission_form[teams][1]');
        $team2->tick();

        $client->submit($form);
        $this->assertIsRedirect($client, $this->createUrl('/admin/project/1/details'));

        /** @var Project $project */
        $project = $em->getRepository(Project::class)->find(1);
        self::assertEquals(2, $project->getTeams()->count());
    }

    public function testDeleteAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new ProjectFixtures();
        $fixture->setAmount(1);
        /** @var Project[] $projects */
        $projects = $this->importFixture($fixture);
        $id = $projects[0]->getId();

        $this->request($client, '/admin/project/' . $id . '/edit');
        self::assertTrue($client->getResponse()->isSuccessful());
        $this->request($client, '/admin/project/' . $id . '/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/project/' . $id . '/delete'), $form->getUri());
        $client->submit($form);

        $client->followRedirect();
        $this->assertHasDataTable($client);
        $this->assertHasFlashSuccess($client);

        $this->request($client, '/admin/project/' . $id . '/edit');
        self::assertFalse($client->getResponse()->isSuccessful());
    }

    public function testDeleteActionWithTimesheetEntries(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

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

        $this->request($client, '/admin/project/1/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/project/1/delete'), $form->getUri());
        $client->submit($form);

        $this->assertIsRedirect($client, $this->createUrl('/admin/project/'));
        $client->followRedirect();
        $this->assertHasFlashDeleteSuccess($client);
        $this->assertHasNoEntriesWithFilter($client);

        $em->clear();
        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(0, \count($timesheets));

        $this->request($client, '/admin/project/1/edit');
        self::assertFalse($client->getResponse()->isSuccessful());
    }

    public function testDeleteActionWithTimesheetEntriesAndReplacement(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $em = $this->getEntityManager();
        $fixture = new TimesheetFixtures();
        $fixture->setUser($this->getUserByRole(User::ROLE_USER));
        $fixture->setAmount(10);
        $this->importFixture($fixture);
        $fixture = new ProjectFixtures();
        $fixture->setAmount(1)->setIsVisible(true);
        $projects = $this->importFixture($fixture);
        $id = $projects[0]->getId();

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(10, \count($timesheets));

        /** @var Timesheet $entry */
        foreach ($timesheets as $entry) {
            self::assertEquals(1, $entry->getProject()->getId());
        }

        $this->request($client, '/admin/project/1/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/project/1/delete'), $form->getUri());
        $client->submit($form, [
            'form' => [
                'project' => $id
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/project/'));
        $client->followRedirect();
        $this->assertHasDataTable($client);
        $this->assertHasFlashSuccess($client);

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(10, \count($timesheets));

        /** @var Timesheet $entry */
        foreach ($timesheets as $entry) {
            self::assertEquals($id, $entry->getProject()->getId());
        }

        $this->request($client, '/admin/project/1/edit');
        self::assertFalse($client->getResponse()->isSuccessful());
    }

    #[DataProvider('getValidationTestData')]
    public function testValidationForCreateAction(array $formData, array $validationFields): void
    {
        $this->assertFormHasValidationError(
            User::ROLE_ADMIN,
            '/admin/project/create',
            'form[name=project_edit_form]',
            $formData,
            $validationFields
        );
    }

    public static function getValidationTestData()
    {
        return [
            [
                [
                    'project_edit_form' => [
                        'name' => '',
                        'customer' => 0,
                    ]
                ],
                [
                    '#project_edit_form_name',
                    '#project_edit_form_customer',
                ]
            ],
        ];
    }

    private function persistFxRate(EntityManager $em, string $indicator, string $date, string $rateValue): FxRate
    {
        $fxRate = new FxRate();
        $fxRate->setDate(new \DateTimeImmutable($date));
        $fxRate->setIndicator($indicator);
        $fxRate->setRateValue($rateValue);
        $em->persist($fxRate);

        return $fxRate;
    }

    private function createMilestone(EntityManager $em, Project $project, string $name, ?string $value = null, ?string $currency = null, ?string $dueDate = null): Milestone
    {
        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName($name);

        if ($value !== null) {
            $milestone->setValue($value);
        }

        if ($currency !== null) {
            $milestone->setCurrency($currency);
        }

        if ($dueDate !== null) {
            $milestone->setDueDate(new \DateTime($dueDate));
        }

        $em->persist($milestone);

        return $milestone;
    }

    public function testDetailsActionRevenueOnlyCountsInvoicedTimesheetsAndInvoicedMilestonesNotJustTheExportedFlag(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        /** @var Project $project */
        $project = $em->getRepository(Project::class)->find(1);
        $user = $this->getUserByRole(User::ROLE_ADMIN);

        $customer = $project->getCustomer();
        self::assertNotNull($customer);
        $customer->setCurrency('CLP');
        $em->persist($customer);

        $activity = new Activity();
        $activity->setName('Revenue test activity');
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

        // billable, never touched by export or invoicing -> must NOT count
        $untouched = new Timesheet();
        $untouched->setProject($project);
        $untouched->setActivity($activity);
        $untouched->setUser($user);
        $untouched->setBegin(new \DateTime('2026-07-01 09:00:00'));
        $untouched->setEnd(new \DateTime('2026-07-01 10:00:00'));
        $untouched->setDuration(3600);
        $untouched->setBillable(true);
        $untouched->setExported(false);
        $untouched->setFixedRate(1000.0);
        $em->persist($untouched);

        // billable AND exported=true, but NEVER invoiced (e.g. included in a
        // plain CSV/Excel export instead) -> must NOT count as revenue. This
        // is exactly the bug: `exported` is shared with the unrelated export
        // feature and is not proof of invoicing.
        $exportedNotInvoiced = new Timesheet();
        $exportedNotInvoiced->setProject($project);
        $exportedNotInvoiced->setActivity($activity);
        $exportedNotInvoiced->setUser($user);
        $exportedNotInvoiced->setBegin(new \DateTime('2026-07-01 11:00:00'));
        $exportedNotInvoiced->setEnd(new \DateTime('2026-07-01 12:00:00'));
        $exportedNotInvoiced->setDuration(3600);
        $exportedNotInvoiced->setBillable(true);
        $exportedNotInvoiced->setExported(true);
        $exportedNotInvoiced->setFixedRate(4000.0);
        $em->persist($exportedNotInvoiced);

        // billable AND actually linked to a real invoice -> must count
        $invoiced = new Timesheet();
        $invoiced->setProject($project);
        $invoiced->setActivity($activity);
        $invoiced->setUser($user);
        $invoiced->setBegin(new \DateTime('2026-07-02 09:00:00'));
        $invoiced->setEnd(new \DateTime('2026-07-02 10:00:00'));
        $invoiced->setDuration(3600);
        $invoiced->setBillable(true);
        $invoiced->setExported(true);
        $invoiced->setFixedRate(2000.0);
        $invoiced->setInvoice($invoice);
        $em->persist($invoiced);

        $invoicedMilestone = $this->createMilestone($em, $project, 'Invoiced milestone', '5000.0000', 'CLP');
        $em->flush();
        $invoicedMilestone->setInvoice($invoice);

        // has a value, but was never invoiced -> must NOT count as revenue
        $this->createMilestone($em, $project, 'Not invoiced milestone', '3000.0000', 'CLP');

        $em->flush();

        $this->assertAccessIsGranted($client, '/admin/project/1/details');

        $row = $client->getCrawler()->filter('div.card#budget_box tr.revenue_row');
        self::assertEquals(1, $row->count());

        // only the invoiced timesheet (2,000) + the invoiced milestone (5,000) = 7,000
        $rawText = $row->filter('td')->eq(1)->text();
        $digitsOnly = preg_replace('/\D/', '', $rawText);
        self::assertSame('7000', $digitsOnly);

        // the hour and milestone components must also be visible on their own
        $hoursRow = $client->getCrawler()->filter('div.card#budget_box tr.timesheet_total_invoiced_row');
        self::assertEquals(1, $hoursRow->count());
        $hoursRowText = $hoursRow->filter('td')->eq(1)->text();
        [$hoursMoneyPart, $hoursDurationPart] = explode('(', $hoursRowText);
        self::assertSame('2000', preg_replace('/\D/', '', $hoursMoneyPart));
        // the single invoiced timesheet was exactly 1 hour (09:00-10:00)
        self::assertStringContainsString('1:00', $hoursDurationPart);

        // milestone_total_row is the potential total of ALL milestones with a
        // value (invoiced or not): 5,000 invoiced + 3,000 not yet invoiced
        $milestoneRow = $client->getCrawler()->filter('div.card#budget_box tr.milestone_total_row');
        self::assertEquals(1, $milestoneRow->count());
        self::assertSame('8000', preg_replace('/\D/', '', $milestoneRow->filter('td')->eq(1)->text()));
    }

    public function testDetailsActionShowsMilestoneClpTotalWhenAllMilestonesConvertible(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        $this->persistFxRate($em, FxRate::INDICATOR_USD, '2026-07-20', '933.920000');

        /** @var Project $project */
        $project = $em->getRepository(Project::class)->find(1);
        $this->createMilestone($em, $project, 'CLP milestone', '1000000.0000', 'CLP');
        $this->createMilestone($em, $project, 'USD milestone', '100.0000', 'USD', '2026-07-20');
        $em->flush();

        $this->assertAccessIsGranted($client, '/admin/project/1/details');

        $row = $client->getCrawler()->filter('div.card#budget_box tr.milestone_total_row');
        self::assertEquals(1, $row->count());
        self::assertEquals(0, $row->filter('span.milestone_total_partial')->count());

        // 1,000,000 CLP (passthrough) + 100 USD * 933.92 = 93,392 CLP -> 1,093,392 CLP total.
        $digitsOnly = preg_replace('/\D/', '', $row->filter('td')->eq(1)->text());
        self::assertSame('1093392', $digitsOnly);
    }

    public function testDetailsActionHidesMilestoneTotalWhenNoMilestoneHasValue(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        /** @var Project $project */
        $project = $em->getRepository(Project::class)->find(1);
        $this->createMilestone($em, $project, 'No value milestone');
        $em->flush();

        $this->assertAccessIsGranted($client, '/admin/project/1/details');

        $row = $client->getCrawler()->filter('div.card#budget_box tr.milestone_total_row');
        self::assertEquals(0, $row->count());
    }

    public function testDetailsActionShowsPartialBadgeWhenSomeMilestonesAreNotConvertible(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        $this->persistFxRate($em, FxRate::INDICATOR_USD, '2026-07-20', '933.920000');

        /** @var Project $project */
        $project = $em->getRepository(Project::class)->find(1);
        $this->createMilestone($em, $project, 'Convertible', '100.0000', 'USD', '2026-07-20');
        // CLF ('uf') has no rate at all in this test's transaction -> not convertible.
        $this->createMilestone($em, $project, 'Not convertible', '50.0000', 'CLF', '2026-07-20');
        $em->flush();

        $this->assertAccessIsGranted($client, '/admin/project/1/details');

        $row = $client->getCrawler()->filter('div.card#budget_box tr.milestone_total_row');
        self::assertEquals(1, $row->count());

        $badge = $row->filter('span.milestone_total_partial');
        self::assertEquals(1, $badge->count());
        self::assertStringContainsString('1', (string) $badge->attr('title'));

        $digitsOnly = preg_replace('/\D/', '', $row->filter('td')->eq(1)->text());
        self::assertSame('93392', $digitsOnly);
    }

    public function testDetailsActionShowsDashWhenEveryValuedMilestoneIsNonConvertible(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        /** @var Project $project */
        $project = $em->getRepository(Project::class)->find(1);
        // No FxRate rows exist at all in this test's transaction -> USD is never convertible.
        $this->createMilestone($em, $project, 'Not convertible', '50.0000', 'USD');
        $em->flush();

        $this->assertAccessIsGranted($client, '/admin/project/1/details');

        $row = $client->getCrawler()->filter('div.card#budget_box tr.milestone_total_row');
        self::assertEquals(1, $row->count());
        self::assertStringContainsString('–', $row->filter('td')->eq(1)->text());
        self::assertEquals(1, $row->filter('span.milestone_total_partial')->count());
    }

    public function testDetailsActionRendersWithoutErrorWhenFxRatesTableIsEmpty(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        // No FxRate fixtures imported at all - the page must not 500.
        $this->assertAccessIsGranted($client, '/admin/project/1/details');
        self::assertTrue($client->getResponse()->isSuccessful());
    }

    public function testDetailsActionHidesMilestoneTotalForUserWithoutBudgetPermission(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $user = $this->getUserByRole(User::ROLE_USER);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        $role = (new Role())->setName('TEST_VIEW_PROJECT_TIME_NO_BUDGET');
        $em->persist($role);
        $em->flush();

        // PermissionService caches all role permissions for a day (App\User\PermissionService).
        // Persisting RolePermission directly through the EntityManager (as the sibling
        // testCreateWithCustomerActionDeniesUserWithoutEditCustomerPermission test does for a
        // denial case) never invalidates that cache, so a newly *granted* permission would not
        // be picked up. Route through PermissionService::saveRolePermission() instead - it
        // deletes the 'permissions' cache entry after every save.
        /** @var PermissionService $permissionService */
        $permissionService = self::getContainer()->get(PermissionService::class);
        $view = (new RolePermission())->setRole($role)->setPermission('view_project')->setAllowed(true);
        $permissionService->saveRolePermission($view);
        $details = (new RolePermission())->setRole($role)->setPermission('details_project')->setAllowed(true);
        $permissionService->saveRolePermission($details);
        // Grants 'time' (but not 'budget') so the shared budgets.html.twig embed
        // still gets included (stats is not null), proving the milestone total
        // row stays hidden through its own is_granted('budget') gate.
        $time = (new RolePermission())->setRole($role)->setPermission('time_project')->setAllowed(true);
        $permissionService->saveRolePermission($time);

        $roleName = $role->getName();
        self::assertNotNull($roleName);
        $user->addRole($roleName);
        $em->persist($user);

        /** @var Project $project */
        $project = $em->getRepository(Project::class)->find(1);
        $this->createMilestone($em, $project, 'Has value', '100.0000', 'CLP');
        $em->flush();

        $this->assertAccessIsGranted($client, '/admin/project/1/details');

        self::assertEquals(0, $client->getCrawler()->filter('div.card#budget_box')->count());
        self::assertEquals(0, $client->getCrawler()->filter('tr.milestone_total_row')->count());
    }

    public function testMilestoneAddActionPersistsValueAndCurrency(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $this->assertAccessIsGranted($client, '/admin/project/1/milestone');
        $form = $client->getCrawler()->filter('form[name=milestone_edit_form]')->form();
        $client->submit($form, [
            'milestone_edit_form' => [
                'name' => 'Delivery milestone',
                'value' => '5000.1234',
                'currency' => 'USD',
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/project/1/details'));
        $client->followRedirect();

        /** @var EntityManager $em */
        $em = $this->getEntityManager();
        /** @var Milestone[] $milestones */
        $milestones = $em->getRepository(Milestone::class)->findBy(['name' => 'Delivery milestone']);
        self::assertCount(1, $milestones);
        self::assertSame('5000.1234', $milestones[0]->getValue());
        self::assertSame('USD', $milestones[0]->getCurrency());
    }

    public function testMilestoneAddActionRejectsValueWithoutCurrency(): void
    {
        $this->assertFormHasValidationError(
            User::ROLE_ADMIN,
            '/admin/project/1/milestone',
            'form[name=milestone_edit_form]',
            [
                'milestone_edit_form' => [
                    'name' => 'Partial milestone',
                    'value' => '100',
                    'currency' => '',
                ]
            ],
            ['#milestone_edit_form_currency']
        );

        $em = $this->getEntityManager();
        self::assertCount(0, $em->getRepository(Milestone::class)->findBy(['name' => 'Partial milestone']));
    }
}
