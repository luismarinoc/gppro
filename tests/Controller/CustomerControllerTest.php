<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\CustomerMeta;
use App\Entity\CustomerRate;
use App\Entity\Invoice;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Tests\DataFixtures\CustomerFixtures;
use App\Tests\DataFixtures\ProjectFixtures;
use App\Tests\DataFixtures\TeamFixtures;
use App\Tests\DataFixtures\TimesheetFixtures;
use App\Tests\Mocks\CustomerTestMetaFieldSubscriberMock;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

#[Group('integration')]
class CustomerControllerTest extends AbstractControllerBaseTestCase
{
    public function testIsSecure(): void
    {
        $this->assertUrlIsSecured('/admin/customer/');
    }

    public function testIsSecureForRole(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/customer/');
    }

    public function testIndexAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $this->assertAccessIsGranted($client, '/admin/customer/');
        $this->assertHasDataTable($client);

        $this->assertPageActions($client, [
            'download toolbar-action' => $this->createUrl('/admin/customer/export'),
        ]);
    }

    public function testIndexActionAsSuperAdmin(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/');
        $this->assertHasDataTable($client);

        $this->assertPageActions($client, [
            'download toolbar-action' => $this->createUrl('/admin/customer/export'),
            'create modal-ajax-form' => $this->createUrl('/admin/customer/create'),
        ]);
    }

    public function testIndexActionWithSearchTermQuery(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new CustomerFixtures();
        $fixture->setAmount(5);
        $fixture->setCallback(function (Customer $customer): void {
            $customer->setVisible(true);
            $customer->setComment('I am a foobar with tralalalala some more content');
            $customer->setMetaField((new CustomerMeta())->setName('location')->setValue('homeoffice'));
            $customer->setMetaField((new CustomerMeta())->setName('feature')->setValue('timetracking'));
        });
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/customer/');

        $this->assertPageActions($client, [
            'download toolbar-action' => $this->createUrl('/admin/customer/export'),
            'create modal-ajax-form' => $this->createUrl('/admin/customer/create'),
        ]);

        $form = $client->getCrawler()->filter('form.searchform')->form();
        $client->submit($form, [
            'searchTerm' => 'feature:timetracking foo',
            'visibility' => 1,
            'size' => 50,
            'page' => 1,
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_customer_admin', 5);
    }

    public function testExportIsSecureForRole(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/admin/customer/export');
    }

    public function testExportAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $this->assertAccessIsGranted($client, '/admin/customer/export');
        $this->assertExcelExportResponse($client, 'gppro-customers_');
    }

    public function testExportActionWithSearchTermQuery(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $this->request($client, '/admin/customer/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form.searchform')->form();
        $form->getFormNode()->setAttribute('action', $this->createUrl('/admin/customer/export'));
        $client->submit($form, [
            'searchTerm' => 'feature:timetracking foo',
            'visibility' => 1,
            'size' => 50,
            'page' => 1,
        ]);

        $this->assertExcelExportResponse($client, 'gppro-customers_');
    }

    public function testDetailsAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/details');
        $this->assertDetailsPage($client);
    }

    public function testDetailsActionRevenueOnlyCountsInvoicedTimesheets(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        /** @var Customer $customer */
        $customer = $em->getRepository(Customer::class)->find(1);
        $customer->setCurrency('CLP');
        $em->persist($customer);
        $em->flush();

        /** @var Project $project */
        $project = $em->getRepository(Project::class)->find(1);
        self::assertSame($customer->getId(), $project->getCustomer()?->getId());

        $user = $this->getUserByRole(User::ROLE_ADMIN);

        $activity = new Activity();
        $activity->setName('Customer revenue test activity ' . uniqid());
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

        $this->assertAccessIsGranted($client, '/admin/customer/' . $customer->getId() . '/details');

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

    public function testDetailsActionRevenueIncludesInvoicedMilestonesAcrossAllCustomerProjects(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        /** @var EntityManager $em */
        $em = $this->getEntityManager();

        /** @var Customer $customer */
        $customer = $em->getRepository(Customer::class)->find(1);
        $customer->setCurrency('CLP');
        $em->persist($customer);
        $em->flush();

        /** @var Project $projectA */
        $projectA = $em->getRepository(Project::class)->find(1);
        self::assertSame($customer->getId(), $projectA->getCustomer()?->getId());

        $projectB = new Project();
        $projectB->setName('Second project ' . uniqid());
        $projectB->setCustomer($customer);
        $em->persist($projectB);
        $em->flush();

        $user = $this->getUserByRole(User::ROLE_ADMIN);

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->setUser($user);
        $invoice->setInvoiceNumber('INV-' . uniqid());
        $invoice->setFilename('invoice-' . uniqid());
        $invoice->setCreatedAt(new \DateTime());
        $invoice->setCurrency('CLP');
        $invoice->setTotal(9000.0);
        $invoice->setVat(0.0);
        $invoice->setTax(0.0);
        $invoice->setDueDays(30);
        $em->persist($invoice);
        $em->flush();

        // invoiced milestone on project A -> must count
        $invoicedA = new Milestone();
        $invoicedA->setProject($projectA);
        $invoicedA->setName('Invoiced milestone A ' . uniqid());
        $invoicedA->setValue('4000.0000');
        $invoicedA->setCurrency('CLP');
        $em->persist($invoicedA);
        $em->flush();
        $invoicedA->setInvoice($invoice);
        $em->persist($invoicedA);

        // invoiced milestone on project B -> must ALSO count (rolled up across every project of the customer)
        $invoicedB = new Milestone();
        $invoicedB->setProject($projectB);
        $invoicedB->setName('Invoiced milestone B ' . uniqid());
        $invoicedB->setValue('5000.0000');
        $invoicedB->setCurrency('CLP');
        $em->persist($invoicedB);
        $em->flush();
        $invoicedB->setInvoice($invoice);
        $em->persist($invoicedB);

        // not yet invoiced milestone on project A -> must NOT count
        $notInvoiced = new Milestone();
        $notInvoiced->setProject($projectA);
        $notInvoiced->setName('Not yet invoiced milestone ' . uniqid());
        $notInvoiced->setValue('3000.0000');
        $notInvoiced->setCurrency('CLP');
        $em->persist($notInvoiced);
        $em->flush();

        $this->assertAccessIsGranted($client, '/admin/customer/' . $customer->getId() . '/details');

        // 4,000 (project A) + 5,000 (project B) = 9,000; the 3,000 not-yet-invoiced milestone is excluded
        $row = $client->getCrawler()->filter('div.card#budget_box tr.revenue_row');
        self::assertEquals(1, $row->count());
        self::assertSame('9000', preg_replace('/\D/', '', $row->filter('td')->eq(1)->text()));

        $milestoneRow = $client->getCrawler()->filter('div.card#budget_box tr.milestone_total_row');
        self::assertEquals(1, $milestoneRow->count());
        self::assertSame('9000', preg_replace('/\D/', '', $milestoneRow->filter('td')->eq(1)->text()));
    }

    private function assertDetailsPage(HttpKernelBrowser $client)
    {
        self::assertHasProgressbar($client);

        $node = $client->getCrawler()->filter('div.card#customer_details_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#project_list_box');
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
        $node = $client->getCrawler()->filter('div.card#customer_rates_box');
        self::assertEquals(1, $node->count());
    }

    public function testAddRateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/rate');
        $form = $client->getCrawler()->filter('form[name=customer_rate_form]')->form();
        $client->submit($form, [
            'customer_rate_form' => [
                'rate' => 123.45,
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.card#customer_rates_box');
        self::assertEquals(1, $node->count());
        $node = $client->getCrawler()->filter('div.card#customer_rates_box table.dataTable tbody tr:not(.summary)');
        self::assertEquals(1, $node->count());
        self::assertStringContainsString('123.45', $node->text(null, true));
    }

    public function testEditRateActionDeniesForeignRate(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $customer = $this->importFixture(new CustomerFixtures(1))[0];
        $rate = new CustomerRate();
        $rate->setCustomer($customer);
        $rate->setRate(123.45);

        $em = $this->getEntityManager();
        $em->persist($rate);
        $em->flush();

        $this->request($client, '/admin/customer/1/rate/' . $rate->getId());

        $this->assertAccessDenied($client);
    }

    public function testAddCommentAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $this->assertAccessIsGranted($client, '/admin/customer/1/details');
        $form = $client->getCrawler()->filter('form[name=customer_comment_form]')->form();
        $client->submit($form, [
            'customer_comment_form' => [
                'message' => 'A beautiful and short comment **with some** markdown formatting',
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();
        $node = $client->getCrawler()->filter('div.card#comments_box .card-body');
        self::assertStringContainsString('A beautiful and short comment **with some** markdown formatting', $node->html());

        $this->setSystemConfiguration('timesheet.markdown_content', true);

        $this->assertAccessIsGranted($client, '/admin/customer/1/details');
        $node = $client->getCrawler()->filter('div.card#comments_box .direct-chat-text');
        self::assertStringContainsString('<p>A beautiful and short comment <strong>with some</strong> markdown formatting</p>', $node->html());
    }

    public function testProjectsAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/projects/1');
        $node = $client->getCrawler()->filter('div.card#project_list_box .card-body table tbody tr');
        self::assertEquals(1, $node->count());

        /** @var EntityManager $em */
        $em = $this->getEntityManager();
        $customer = $em->getRepository(Customer::class)->find(1);

        $fixture = new ProjectFixtures();
        $fixture->setAmount(9); // to trigger a second page (every third activity is hidden)
        $fixture->setCustomers([$customer]);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/customer/1/projects/1');

        $node = $client->getCrawler()->filter('div.card#project_list_box .card-footer ul.pagination li');
        self::assertEquals(4, $node->count());

        $node = $client->getCrawler()->filter('div.card#project_list_box .card-body table tbody tr');
        self::assertEquals(5, $node->count());
    }

    public function testCreateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/create');
        $form = $client->getCrawler()->filter('form[name=customer_edit_form]')->form();

        $editForm = $client->getCrawler()->filter('form[name=customer_edit_form]')->form();
        self::assertEquals(date_default_timezone_get(), $editForm->get('customer_edit_form[timezone]')->getValue());

        $client->submit($form, [
            'customer_edit_form' => [
                'name' => 'Test Customer',
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
        $dispatcher->addSubscriber(new CustomerTestMetaFieldSubscriberMock());
        $this->assertAccessIsGranted($client, '/admin/customer/create');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=customer_edit_form]')->form();
        self::assertTrue($form->has('customer_edit_form[metaFields][metatestmock][value]'));
        self::assertTrue($form->has('customer_edit_form[metaFields][foobar][value]'));
        self::assertFalse($form->has('customer_edit_form[metaFields][0][value]'));
    }

    public function testEditAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/customer/1/edit');
        $form = $client->getCrawler()->filter('form[name=customer_edit_form]')->form();
        self::assertEquals('Test', $form->get('customer_edit_form[name]')->getValue());
        $client->submit($form, [
            'customer_edit_form' => [
                'name' => 'Test Customer 2'
            ]
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));
        $client->followRedirect();
        $this->request($client, '/admin/customer/1/edit');
        $editForm = $client->getCrawler()->filter('form[name=customer_edit_form]')->form();
        self::assertEquals('Test Customer 2', $editForm->get('customer_edit_form[name]')->getValue());
    }

    public function testTeamPermissionAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        /** @var Customer $customer */
        $customer = $em->getRepository(Customer::class)->find(1);
        self::assertEquals(0, $customer->getTeams()->count());

        $fixture = new TeamFixtures();
        $fixture->setAmount(2);
        $fixture->setAddCustomer(false);
        $this->importFixture($fixture);

        $this->assertAccessIsGranted($client, '/admin/customer/1/permissions');
        $form = $client->getCrawler()->filter('form[name=customer_team_permission_form]')->form();
        /** @var ChoiceFormField $team1 */
        $team1 = $form->get('customer_team_permission_form[teams][0]');
        $team1->tick();
        /** @var ChoiceFormField $team2 */
        $team2 = $form->get('customer_team_permission_form[teams][1]');
        $team2->tick();

        $client->submit($form);
        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/1/details'));

        /** @var Customer $customer */
        $customer = $em->getRepository(Customer::class)->find(1);
        self::assertEquals(2, $customer->getTeams()->count());
    }

    public function testDeleteAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new CustomerFixtures();
        $fixture->setAmount(1);
        $customers = $this->importFixture($fixture);
        $customer = $customers[0];
        $id = $customer->getId();

        $this->request($client, '/admin/customer/' . $id . '/edit');
        self::assertTrue($client->getResponse()->isSuccessful());
        $this->request($client, '/admin/customer/' . $id . '/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/customer/' . $id . '/delete'), $form->getUri());
        $client->submit($form);

        $client->followRedirect();
        $this->assertHasDataTable($client);
        $this->assertHasFlashSuccess($client);

        $this->request($client, '/admin/customer/' . $id . '/edit');
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

        $this->request($client, '/admin/customer/1/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/customer/1/delete'), $form->getUri());
        $client->submit($form);

        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/'));
        $client->followRedirect();
        $this->assertHasFlashDeleteSuccess($client);
        $this->assertHasNoEntriesWithFilter($client);

        $em->clear();
        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(0, \count($timesheets));

        $this->request($client, '/admin/customer/1/edit');
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
        $fixture = new CustomerFixtures();
        $fixture->setAmount(1)->setIsVisible(true);
        $customers = $this->importFixture($fixture);
        $customer = $customers[0];
        $id = $customer->getId();

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(10, \count($timesheets));

        /** @var Timesheet $entry */
        foreach ($timesheets as $entry) {
            self::assertEquals(1, $entry->getProject()->getCustomer()->getId());
        }

        $this->request($client, '/admin/customer/1/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/customer/1/delete'), $form->getUri());
        $client->submit($form, [
            'form' => [
                'customer' => $id
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/customer/'));
        $client->followRedirect();
        $this->assertHasDataTable($client);
        $this->assertHasFlashSuccess($client);

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(10, \count($timesheets));

        /** @var Timesheet $entry */
        foreach ($timesheets as $entry) {
            self::assertEquals($id, $entry->getProject()->getCustomer()->getId());
        }

        $this->request($client, '/admin/customer/1/edit');
        self::assertFalse($client->getResponse()->isSuccessful());
    }

    #[DataProvider('getValidationTestData')]
    public function testValidationForCreateAction(array $formData, array $validationFields): void
    {
        $this->assertFormHasValidationError(
            User::ROLE_ADMIN,
            '/admin/customer/create',
            'form[name=customer_edit_form]',
            $formData,
            $validationFields
        );
    }

    public static function getValidationTestData()
    {
        return [
            [
                [
                    'customer_edit_form' => [
                        'name' => '',
                        'country' => '00',
                        'currency' => '00',
                        'timezone' => 'XXX'
                    ]
                ],
                [
                    '#customer_edit_form_name',
                    '#customer_edit_form_country',
                    '#customer_edit_form_currency',
                    '#customer_edit_form_timezone',
                ]
            ],
        ];
    }
}
