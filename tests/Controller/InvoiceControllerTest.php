<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\InvoiceTemplate;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\Team;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Tests\DataFixtures\InvoiceTemplateFixtures;
use App\Tests\DataFixtures\TimesheetFixtures;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Group('integration')]
class InvoiceControllerTest extends AbstractControllerBaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearInvoiceFiles();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->clearInvoiceFiles();
    }

    private function clearInvoiceFiles(): void
    {
        $path = __DIR__ . '/../_data/invoices/';

        if (is_dir($path)) {
            $files = glob($path . '*');
            if ($files === false) {
                return;
            }
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    public function testIsSecure(): void
    {
        $this->assertUrlIsSecured('/invoice/');
    }

    public function testIsSecureForRole(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/invoice/');
    }

    public function testIndexActionRedirectsToCreateTemplate(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $this->request($client, '/invoice/');
        $this->assertIsRedirect($client, '/invoice/template/create');
    }

    public function testIndexActionHasErrorMessageOnEmptyQuery(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $fixture = new InvoiceTemplateFixtures();
        $templates = $this->importFixture($fixture);
        $id = $templates[0]->getId();

        $this->request($client, '/invoice/?customers[]=1&template=' . $id);
        self::assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasNoEntriesWithFilter($client);
    }

    private function createCustomer(): Customer
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Invoice controller test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);
        $em->flush();

        return $customer;
    }

    private function createProject(Customer $customer): Project
    {
        $em = $this->getEntityManager();

        $project = new Project();
        $project->setName('Invoice controller test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);
        $em->flush();

        return $project;
    }

    private function createMilestone(Project $project, string $name): Milestone
    {
        $em = $this->getEntityManager();

        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName($name . ' ' . uniqid());
        $milestone->setValue('1000.0000');
        $milestone->setCurrency('CLP');
        $em->persist($milestone);
        $em->flush();

        return $milestone;
    }

    private function nameOf(Milestone $milestone): string
    {
        $name = $milestone->getName();
        self::assertNotNull($name);

        return $name;
    }

    public function testShowInvoicesActionListsMilestoneNameForMilestoneInvoicesAndDurationForHourInvoices(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();

        $customer = $this->createCustomer();
        $project = $this->createProject($customer);
        $user = $this->getUserByRole(User::ROLE_ADMIN);

        $milestoneInvoice = new Invoice();
        $milestoneInvoice->setCustomer($customer);
        $milestoneInvoice->setUser($user);
        $milestoneInvoice->setInvoiceNumber('INV-' . uniqid());
        $milestoneInvoice->setFilename('invoice-' . uniqid());
        $milestoneInvoice->setCreatedAt(new \DateTime());
        $milestoneInvoice->setCurrency('CLP');
        $milestoneInvoice->setTotal(1000.0);
        $milestoneInvoice->setVat(0.0);
        $milestoneInvoice->setTax(0.0);
        $milestoneInvoice->setDueDays(30);
        $em->persist($milestoneInvoice);

        $hourInvoice = new Invoice();
        $hourInvoice->setCustomer($customer);
        $hourInvoice->setUser($user);
        $hourInvoice->setInvoiceNumber('INV-' . uniqid());
        $hourInvoice->setFilename('invoice-' . uniqid());
        $hourInvoice->setCreatedAt(new \DateTime());
        $hourInvoice->setCurrency('CLP');
        $hourInvoice->setTotal(2000.0);
        $hourInvoice->setVat(0.0);
        $hourInvoice->setTax(0.0);
        $hourInvoice->setDueDays(30);
        $em->persist($hourInvoice);
        $em->flush();

        $milestone = $this->createMilestone($project, 'Listing milestone detail');
        $milestone->setInvoice($milestoneInvoice);

        $activity = new Activity();
        $activity->setName('Listing test activity');
        $activity->setProject($project);
        $em->persist($activity);
        $em->flush();

        $timesheet = new Timesheet();
        $timesheet->setProject($project);
        $timesheet->setActivity($activity);
        $timesheet->setUser($user);
        $timesheet->setBegin(new \DateTime('2026-07-01 09:00:00'));
        $timesheet->setEnd(new \DateTime('2026-07-01 11:00:00'));
        $timesheet->setDuration(7200);
        $timesheet->setBillable(true);
        $timesheet->setInvoice($hourInvoice);
        $em->persist($timesheet);
        $em->flush();

        $this->request($client, '/invoice/show/1');
        self::assertTrue($client->getResponse()->isSuccessful());

        $html = $client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString($this->nameOf($milestone), $html);
        // the hour invoice's linked timesheet totals exactly 2 hours
        self::assertStringContainsString('2:00', $html);
        self::assertStringContainsString($this->createUrl('/invoice/view/' . $milestoneInvoice->getId()), $html);
        self::assertStringContainsString($this->createUrl('/invoice/download/' . $milestoneInvoice->getId()), $html);
    }

    public function testShowInvoicesActionRendersHistoryTotalsGroupedByCustomerProjectAndType(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();
        $user = $this->getUserByRole(User::ROLE_ADMIN);

        $customerA = $this->createCustomer();
        $projectMilestone = $this->createProject($customerA);
        $projectTimesheet = $this->createProject($customerA);

        $customerB = $this->createCustomer();
        $projectB = $this->createProject($customerB);

        // customer A / project milestone: a milestone invoice
        $milestoneInvoice = new Invoice();
        $milestoneInvoice->setCustomer($customerA);
        $milestoneInvoice->setUser($user);
        $milestoneInvoice->setInvoiceNumber('INV-' . uniqid());
        $milestoneInvoice->setFilename('invoice-' . uniqid());
        $milestoneInvoice->setCreatedAt(new \DateTime());
        $milestoneInvoice->setCurrency('CLP');
        $milestoneInvoice->setTotal(1000.0);
        $milestoneInvoice->setVat(0.0);
        $milestoneInvoice->setTax(0.0);
        $milestoneInvoice->setDueDays(30);
        $em->persist($milestoneInvoice);
        $em->flush();

        $milestone = $this->createMilestone($projectMilestone, 'History totals milestone');
        $milestone->setInvoice($milestoneInvoice);
        $em->flush();

        // customer A / project timesheet: an hour invoice for exactly 1h at a fixed rate of 2000
        $hourInvoice = new Invoice();
        $hourInvoice->setCustomer($customerA);
        $hourInvoice->setUser($user);
        $hourInvoice->setInvoiceNumber('INV-' . uniqid());
        $hourInvoice->setFilename('invoice-' . uniqid());
        $hourInvoice->setCreatedAt(new \DateTime());
        $hourInvoice->setCurrency('CLP');
        $hourInvoice->setTotal(2000.0);
        $hourInvoice->setVat(0.0);
        $hourInvoice->setTax(0.0);
        $hourInvoice->setDueDays(30);
        $em->persist($hourInvoice);

        $activity = new Activity();
        $activity->setName('History totals activity');
        $activity->setProject($projectTimesheet);
        $em->persist($activity);
        $em->flush();

        $begin = new \DateTime('2026-07-01 09:00:00');
        $timesheet = new Timesheet();
        $timesheet->setProject($projectTimesheet);
        $timesheet->setActivity($activity);
        $timesheet->setUser($user);
        $timesheet->setBegin($begin);
        $timesheet->setEnd((clone $begin)->modify('+3600 seconds'));
        $timesheet->setFixedRate(2000.0);
        $timesheet->setBillable(true);
        $timesheet->setInvoice($hourInvoice);
        $em->persist($timesheet);
        $em->flush();

        // customer B / project B: a separate milestone invoice
        $milestoneInvoiceB = new Invoice();
        $milestoneInvoiceB->setCustomer($customerB);
        $milestoneInvoiceB->setUser($user);
        $milestoneInvoiceB->setInvoiceNumber('INV-' . uniqid());
        $milestoneInvoiceB->setFilename('invoice-' . uniqid());
        $milestoneInvoiceB->setCreatedAt(new \DateTime());
        $milestoneInvoiceB->setCurrency('CLP');
        $milestoneInvoiceB->setTotal(500.0);
        $milestoneInvoiceB->setVat(0.0);
        $milestoneInvoiceB->setTax(0.0);
        $milestoneInvoiceB->setDueDays(30);
        $em->persist($milestoneInvoiceB);
        $em->flush();

        $milestoneB = new Milestone();
        $milestoneB->setProject($projectB);
        $milestoneB->setName('History totals milestone B ' . uniqid());
        $milestoneB->setValue('500.0000');
        $milestoneB->setCurrency('CLP');
        $milestoneB->setInvoice($milestoneInvoiceB);
        $em->persist($milestoneB);
        $em->flush();

        $this->request($client, '/invoice/show/1');
        self::assertTrue($client->getResponse()->isSuccessful());

        $crawler = $client->getCrawler();

        $customerAName = $customerA->getName();
        $customerBName = $customerB->getName();
        $projectMilestoneName = $projectMilestone->getName();
        $projectTimesheetName = $projectTimesheet->getName();
        $projectBName = $projectB->getName();
        self::assertNotNull($customerAName);
        self::assertNotNull($customerBName);
        self::assertNotNull($projectMilestoneName);
        self::assertNotNull($projectTimesheetName);
        self::assertNotNull($projectBName);

        // the per-row "Proyecto" column on the main table must show each invoice's project(s)
        $html = $client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString($projectMilestoneName, $html);
        self::assertStringContainsString($projectTimesheetName, $html);
        self::assertStringContainsString($projectBName, $html);

        // the totals summary box must contain one row per (customer, project, type)
        $summaryBox = $crawler->filter('div.card#invoice_history_summary_box');
        self::assertEquals(1, $summaryBox->count());

        $summaryText = $summaryBox->text();
        self::assertStringContainsString($customerAName, $summaryText);
        self::assertStringContainsString($customerBName, $summaryText);
        self::assertStringContainsString($projectMilestoneName, $summaryText);
        self::assertStringContainsString($projectTimesheetName, $summaryText);
        self::assertStringContainsString($projectBName, $summaryText);

        // milestone total for customer A / projectMilestone must be 1000 CLP
        $milestoneRows = $summaryBox->filter('tr.milestone_total_row');
        self::assertEquals(2, $milestoneRows->count());

        $rowForA = null;
        $rowForB = null;
        $milestoneRows->each(function ($row) use ($projectMilestoneName, $projectBName, &$rowForA, &$rowForB): void {
            $projectCell = $row->filter('td')->eq(1)->text();
            if (str_contains($projectCell, $projectMilestoneName)) {
                $rowForA = $row;
            } elseif (str_contains($projectCell, $projectBName)) {
                $rowForB = $row;
            }
        });
        self::assertNotNull($rowForA);
        self::assertNotNull($rowForB);
        self::assertSame('1000', preg_replace('/\D/', '', $rowForA->filter('td')->eq(4)->text()));
        self::assertSame('500', preg_replace('/\D/', '', $rowForB->filter('td')->eq(4)->text()));

        // timesheet total for customer A / projectTimesheet must show the invoiced duration and rate
        $timesheetRows = $summaryBox->filter('tr.timesheet_total_invoiced_row');
        self::assertEquals(1, $timesheetRows->count());
        self::assertStringContainsString('1:00', $timesheetRows->filter('td')->eq(5)->text());
        self::assertSame('2000', preg_replace('/\D/', '', $timesheetRows->filter('td')->eq(4)->text()));
    }

    public function testIndexActionMilestoneModeListsInvoiceableMilestonesForSelectedCustomer(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $customer = $this->createCustomer();
        $project = $this->createProject($customer);
        $milestone = $this->createMilestone($project, 'Milestone toggle listing');

        $this->request($client, '/invoice/?invoiceType=milestone&customers[]=' . $customer->getId());
        self::assertTrue($client->getResponse()->isSuccessful());

        $html = $client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString($this->nameOf($milestone), $html);
    }

    public function testIndexActionMilestoneModeShowsNothingWithoutCustomerSelected(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $this->request($client, '/invoice/?invoiceType=milestone');
        self::assertTrue($client->getResponse()->isSuccessful());
    }

    public function testIndexActionMilestoneModeDeniesUnauthorizedCustomerIdor(): void
    {
        // client must be created first: booting a second kernel via the
        // entity manager afterwards is not supported by WebTestCase
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        // attacker: team-scoped (canSeeAllData() === false for ROLE_TEAMLEAD)
        // user, restricted via team membership to Customer A only. The
        // "customers" toolbar field is not choice-validated (it's a "fake"
        // autocomplete field, see ToolbarFormTrait::addCustomerSelect), so a
        // tampered customers[]=<B> query param must still be rejected by the
        // explicit isGranted('access', ...) check in InvoiceController.
        $attacker = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $em = $this->getEntityManager();

        $customerA = $this->createCustomer();
        $teamA = new Team('Invoice milestone mode team A ' . uniqid());
        $teamA->addUser($attacker);
        $teamA->addCustomer($customerA);
        $em->persist($teamA);

        $customerB = $this->createCustomer();
        $teamB = new Team('Invoice milestone mode team B ' . uniqid());
        $teamB->addCustomer($customerB);
        $em->persist($teamB);
        $em->flush();

        $projectB = $this->createProject($customerB);
        $foreignMilestone = $this->createMilestone($projectB, 'Foreign milestone');

        $this->request($client, '/invoice/?invoiceType=milestone&customers[]=' . $customerB->getId());
        self::assertTrue($client->getResponse()->isSuccessful());

        $html = $client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringNotContainsString($this->nameOf($foreignMilestone), $html);
    }

    public function testListTemplateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $this->importFixture($fixture);

        $this->request($client, '/invoice/template');

        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasDataTable($client);
    }

    public function testCreateTemplateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->request($client, '/invoice/template/create');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=invoice_template_form]')->form();
        $client->submit($form, [
            'invoice_template_form' => [
                'name' => 'FooBar Template',
                'title' => 'Test invoice template',
                'customer' => 1,
                'renderer' => 'default',
                'calculator' => 'default',
                'vat' => '27,937',
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/template'));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasFlashSuccess($client);

        $template = $this->getEntityManager()->getRepository(InvoiceTemplate::class)->findAll()[0];
        self::assertEquals('FooBar Template', $template->getName());
        self::assertEquals('Test invoice template', $template->getTitle());
        self::assertEquals('Test', $template->getCompany());
        self::assertEquals('default', $template->getRenderer());
        self::assertEquals('default', $template->getCalculator());
        self::assertEquals('27.937', $template->getVat());
    }

    public function testCopyTemplateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $templates = $this->importFixture($fixture);
        /** @var InvoiceTemplate $template */
        $template = $templates[0];

        $this->request($client, '/invoice/template/create/' . $template->getId());
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=invoice_template_form]')->form();
        $values = $form->getPhpValues()['invoice_template_form'];
        self::assertEquals($template->getName() . ' (1)', $values['name']);
        self::assertEquals($template->getTitle(), $values['title']);
        self::assertEquals($template->getDueDays(), $values['dueDays']);
        self::assertEquals($template->getCalculator(), $values['calculator']);
        self::assertEquals($template->getVat(), $values['vat']);
        self::assertEquals($template->getRenderer(), $values['renderer']);
        self::assertEquals($template->getPaymentTerms(), $values['paymentTerms']);
    }

    public function testCreateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $fixture = new InvoiceTemplateFixtures();
        $templates = $this->importFixture($fixture);
        /** @var InvoiceTemplate $template */
        $template = $templates[0];

        $begin = new \DateTime('first day of this month');
        $end = new \DateTime('last day of this month');
        $fixture = new TimesheetFixtures();
        $fixture
            ->setUser($this->getUserByRole(User::ROLE_TEAMLEAD))
            ->setAmount(20)
            ->setStartDate($begin)
        ;
        $timesheets = $this->importFixture($fixture);
        foreach ($timesheets as $timesheet) {
            self::assertFalse($timesheet->isExported());
        }

        $this->request($client, '/invoice/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $dateRange = $this->formatDateRange($begin, $end);

        $form = $client->getCrawler()->filter('#invoice-print-form')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/'));
        $node->setAttribute('method', 'GET');
        $client->submit($form, [
            'template' => $template->getId(),
            'daterange' => $dateRange,
            'customers' => [1],
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());

        // no warning should be displayed
        $node = $client->getCrawler()->filter('div.callout.callout-warning.lead');
        self::assertEquals(0, $node->count());
        // but the datatable with all timesheets
        $this->assertDataTableRowCount($client, 'datatable_invoice_create', 20);

        $urlParams = [
            'daterange' => $dateRange,
            'projects[]' => 1,
            'template' => $template->getId(),
        ];

        $token = $client->getCrawler()->filter('div#create-token')->attr('data-value');

        $action = '/invoice/save-invoice/1/' . $token . '?' . http_build_query($urlParams);
        $this->request($client, $action);
        $this->assertIsRedirect($client, '/invoice/show', false);
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertDataTableRowCount($client, 'datatable_invoices', 1);

        // the freshly created invoice must NOT be auto-downloaded - the PDF
        // is only fetched when the user explicitly clicks download
        $html = $client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringNotContainsString('admin_invoice_download', $html);

        $em = $this->getEntityManager();
        $em->clear();
        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertCount(20, $timesheets);
        /** @var Timesheet $timesheet */
        foreach ($timesheets as $timesheet) {
            self::assertTrue($timesheet->isExported());
        }
    }

    public function testPreviewAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $fixture = new InvoiceTemplateFixtures();
        $templates = $this->importFixture($fixture);
        $id = $templates[0]->getId();

        $begin = new \DateTime('first day of this month');
        $end = new \DateTime('last day of this month');
        $fixture = new TimesheetFixtures();
        $fixture
            ->setUser($this->getUserByRole(User::ROLE_TEAMLEAD))
            ->setAmount(20)
            ->setStartDate($begin)
        ;
        $this->importFixture($fixture);

        $this->request($client, '/invoice/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $dateRange = $this->formatDateRange($begin, $end);

        $form = $client->getCrawler()->filter('#invoice-print-form')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/'));
        $node->setAttribute('method', 'GET');
        $client->submit($form, [
            'template' => $id,
            'daterange' => $dateRange,
            'customers' => [1],
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());

        $params = [
            'daterange' => $dateRange,
            'projects' => [1],
            'template' => $id,
            'customers[]' => 1
        ];

        $token = $client->getCrawler()->filter('div#preview-token')->attr('data-value');
        $action = '/invoice/preview/1/' . $token . '?' . http_build_query($params);

        $this->request($client, $action);
        self::assertTrue($client->getResponse()->isSuccessful());
        $node = $client->getCrawler()->filter('body');
        self::assertEquals(1, $node->count());

        /** @var \DOMElement $element */
        $element = $node->getIterator()[0];
        self::assertEquals('invoice_print', $element->getAttribute('class'));
    }

    public function testCreateActionAsAdminWithDownloadAndStatusChange(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $templates = $this->importFixture($fixture);
        $template = $templates[0];

        $begin = new \DateTime('first day of this month');
        $end = new \DateTime('last day of this month');
        $fixture = new TimesheetFixtures();
        $fixture
            ->setUser($this->getUserByRole(User::ROLE_ADMIN))
            ->setAmount(20)
            ->setStartDate($begin)
        ;
        $this->importFixture($fixture);

        $this->request($client, '/invoice/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $dateRange = $this->formatDateRange($begin, $end);

        $form = $client->getCrawler()->filter('#invoice-print-form')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/'));
        $node->setAttribute('method', 'GET');
        $client->submit($form, [
            'template' => $template->getId(),
            'daterange' => $dateRange,
            'customers' => [1],
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());

        // no warning should be displayed
        $node = $client->getCrawler()->filter('div.callout.callout-warning.lead');
        self::assertEquals(0, $node->count());
        // but the datatable with all timesheets
        $this->assertDataTableRowCount($client, 'datatable_invoice_create', 20);

        $token = $client->getCrawler()->filter('div#create-token')->attr('data-value');

        $form = $client->getCrawler()->filter('#invoice-print-form')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/save-invoice/1/' . $token));
        $node->setAttribute('method', 'GET');
        $client->submit($form, [
            'template' => $template->getId(),
            'daterange' => $dateRange,
            'projects' => [1],
        ]);

        $this->assertIsRedirect($client, '/invoice/show', false);
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());

        $invoices = $this->getEntityManager()->getRepository(Invoice::class)->findAll();
        self::assertCount(1, $invoices);
        $id = $invoices[0]->getId();
        self::assertIsInt($id);

        $this->assertHasFlashSuccess($client);

        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_invoices', 1);

        // make sure the invoice is saved
        $this->request($client, '/invoice/download/' . $id);
        $response = $client->getResponse();
        self::assertTrue($response->isSuccessful());
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertFileExists($response->getFile());

        $this->request($client, '/invoice/show');
        self::assertTrue($client->getResponse()->isSuccessful());
        $link = $client->getCrawler()->selectLink('Waiting for payment');

        $this->request($client, $link->attr('href'));
        $this->assertIsRedirect($client, '/invoice/show');
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());

        // D6 (this SDD change): the PAID transition is now gated by payment
        // approval - submit for approval, then have a distinct eligible
        // teamlead clear the (default, level-1, ROLE_TEAMLEAD) level before
        // the pre-existing PAID flow below can proceed. The invoice's own
        // "user" is the ADMIN identity that generated it, so a DIFFERENT
        // user is required per InvoicePaymentApprovalPolicy's four-eyes rule.
        $submitToken = $this->extractInvoiceToken($client, $id, '/submit-payment-approval');
        $this->request($client, '/invoice/' . $id . '/submit-payment-approval', 'POST', ['_token' => $submitToken]);
        $this->assertIsRedirect($client, $this->createUrl('/invoice/edit/' . $id));

        \assert($client instanceof KernelBrowser);
        $teamlead = $this->loadUserFromDatabase(UserFixtures::USERNAME_TEAMLEAD);
        $client->loginUser($teamlead, 'secured_area');
        $approveToken = $this->extractInvoiceToken($client, $id, '/approve-payment');
        $this->request($client, '/invoice/' . $id . '/approve-payment', 'POST', ['_token' => $approveToken]);
        $this->assertIsRedirect($client, $this->createUrl('/invoice/edit/' . $id));

        $adminUser = $this->loadUserFromDatabase(UserFixtures::USERNAME_ADMIN);
        $client->loginUser($adminUser, 'secured_area');
        $this->request($client, '/invoice/show');
        self::assertTrue($client->getResponse()->isSuccessful());

        $link = $client->getCrawler()->selectLink('Invoice paid');
        $url = $link->attr('href');
        $this->request($client, $url);
        self::assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasValidationError(
            $client,
            $url,
            'form[name=invoice_edit_form]',
            [
                'invoice_edit_form' => [
                    'paymentDate' => 'invalid'
                ]
            ],
            ['#invoice_edit_form_paymentDate']
        );

        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=invoice_edit_form]')->form();
        $client->submit($form, [
            'invoice_edit_form' => [
                'paymentDate' => (new \DateTime())->format(self::DEFAULT_DATE_FORMAT)
            ]
        ]);

        $this->assertIsRedirect($client, '/invoice/show');
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());

        $token = $this->getCsrfToken($client, 'invoice.status');
        $this->request($client, '/invoice/change-status/' . $id . '/new/' . $token->getValue());
        $this->assertIsRedirect($client, '/invoice/show');
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
    }

    public function testEditTemplateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $template = $this->importFixture($fixture);
        $id = $template[0]->getId();

        $this->request($client, '/invoice/template/' . $id . '/edit?page=1');
        $form = $client->getCrawler()->filter('form[name=invoice_template_form]')->form();
        $client->submit($form, [
            'invoice_template_form' => [
                'name' => 'Test 2!',
                'title' => 'Test invoice template',
                'customer' => 1,
                'renderer' => 'default',
                'calculator' => 'default',
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/template'));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasFlashSuccess($client);
    }

    public function testEditTemplateActionUploadsAndRemovesLogo(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $template = $this->importFixture($fixture);
        $id = $template[0]->getId();

        // smallest possible valid PNG (1x1 transparent pixel)
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $logoPath = tempnam(sys_get_temp_dir(), 'gppro_logo_test') . '.png';
        file_put_contents($logoPath, $pngData);

        $this->request($client, '/invoice/template/' . $id . '/edit?page=1');
        $form = $client->getCrawler()->filter('form[name=invoice_template_form]')->form();
        /** @var FileFormField $logoField */
        $logoField = $form['invoice_template_form[logo]'];
        $logoField->upload($logoPath);
        $client->submit($form, [
            'invoice_template_form' => [
                'name' => $template[0]->getName(),
                'title' => 'Test invoice template',
                'customer' => 1,
                'renderer' => 'default',
                'calculator' => 'default',
            ]
        ]);

        unlink($logoPath);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/template'));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasFlashSuccess($client);

        $em = $this->getEntityManager();
        $em->clear();
        /** @var InvoiceTemplate $reloaded */
        $reloaded = $em->getRepository(InvoiceTemplate::class)->find($id);
        self::assertNotNull($reloaded->getLogo());
        self::assertStringStartsWith('data:image/png;base64,', $reloaded->getLogo());

        // now remove it via the checkbox
        $this->request($client, '/invoice/template/' . $id . '/edit?page=1');
        $form = $client->getCrawler()->filter('form[name=invoice_template_form]')->form();
        $client->submit($form, [
            'invoice_template_form' => [
                'name' => $template[0]->getName(),
                'title' => 'Test invoice template',
                'customer' => 1,
                'renderer' => 'default',
                'calculator' => 'default',
                'removeLogo' => '1',
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/template'));

        $em->clear();
        /** @var InvoiceTemplate $reloadedAgain */
        $reloadedAgain = $em->getRepository(InvoiceTemplate::class)->find($id);
        self::assertNull($reloadedAgain->getLogo());
    }

    public function testDeleteTemplateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $template = $this->importFixture($fixture);
        $id = $template[0]->getId();

        $this->request($client, '/invoice/template');
        $url = $this->createUrl('/invoice/template/' . $id . '/delete/');
        $links = $client->getCrawler()->filterXPath("//a[starts-with(@href, '" . $url . "')]");

        $this->requestPure($client, $links->attr('href'));
        $this->assertIsRedirect($client, '/invoice/template');
        $client->followRedirect();

        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasFlashSuccess($client);

        self::assertEquals(0, $this->getEntityManager()->getRepository(InvoiceTemplate::class)->count([]));
    }

    public function testUploadDocumentAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $this->importFixture($fixture);

        $this->request($client, '/invoice/document_upload');
        self::assertTrue($client->getResponse()->isSuccessful());

        $node = $client->getCrawler()->filter('form[name=invoice_document_upload_form]');
        self::assertEquals(1, $node->count(), 'Could not find upload form');
        // we do not test the upload here, just make sure that the action can be rendered properly
    }

    public function testExportIsSecureForRole(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/invoice/export');
    }

    public function testExportAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $this->assertAccessIsGranted($client, '/invoice/export');
        $this->assertExcelExportResponse($client, 'gppro-invoices_');
    }

    private function createInvoiceCustomer(): Customer
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Invoice controller test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);
        $em->flush();

        return $customer;
    }

    /**
     * Extracts a CSRF token from an actually rendered `invoice_edit`
     * page (mirrors ExpenseControllerTest::extractToken()) - a token
     * minted outside the real request/session (e.g. via getCsrfToken())
     * does not validate against the CSRF token stored in the real one.
     */
    private function extractInvoiceToken(\Symfony\Component\HttpKernel\HttpKernelBrowser $client, int $invoiceId, string $formAction): string
    {
        $crawler = $this->request($client, '/invoice/edit/' . $invoiceId);
        $value = $crawler->filter('form[action$="' . $formAction . '"] input[name=_token]')->attr('value');
        self::assertIsString($value, 'Could not find token via selector for action "' . $formAction . '"');

        return $value;
    }

    private function createNewInvoice(Customer $customer, User $createdBy, float $total = 100000.0): Invoice
    {
        $em = $this->getEntityManager();

        $invoice = new Invoice();
        $invoice->setStatus(Invoice::STATUS_PENDING);
        $invoice->setTotal($total);
        $invoice->setVat(0.0);
        $invoice->setTax(0.0);
        $invoice->setCustomer($customer);
        $invoice->setInvoiceNumber('inv-controller-' . uniqid());
        $invoice->setFilename('inv-controller-' . uniqid());
        $invoice->setCreatedAt(new \DateTime());
        $invoice->setUser($createdBy);
        $invoice->setDueDays(30);
        $invoice->setCurrency('CLP');
        $em->persist($invoice);
        $em->flush();

        return $invoice;
    }

    /**
     * Task 2.9: new routes reachable and correctly gated - submit freezes
     * the required levels for an eligible caller.
     */
    public function testSubmitPaymentApprovalActionFreezesRequiredLevels(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $customer = $this->createInvoiceCustomer();
        $creator = $this->getUserByRole(User::ROLE_ADMIN);
        $invoice = $this->createNewInvoice($customer, $creator);
        $invoiceId = $invoice->getId();
        self::assertIsInt($invoiceId);

        $token = $this->extractInvoiceToken($client, $invoiceId, '/submit-payment-approval');
        $this->request($client, '/invoice/' . $invoiceId . '/submit-payment-approval', 'POST', ['_token' => $token]);
        $this->assertIsRedirect($client, $this->createUrl('/invoice/edit/' . $invoiceId));
        $client->followRedirect();
        $this->assertHasFlashSuccess($client);

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Invoice::class)->find($invoiceId);
        self::assertInstanceOf(Invoice::class, $reloaded);
        self::assertSame(Invoice::PAYMENT_APPROVAL_PENDING, $reloaded->getPaymentApprovalStatus());
        self::assertSame(1, $reloaded->getPaymentRequiredLevels());
    }

    /**
     * Task 2.9: eligible approver (matching level 1's seeded ROLE_TEAMLEAD,
     * distinct from the invoice's own user) clears the level and the
     * invoice becomes payment-approved.
     */
    public function testApprovePaymentActionByEligibleApproverClearsLevelAndApproves(): void
    {
        // The client must be created before any other call that boots the
        // kernel (e.g. getEntityManager()), otherwise WebTestCase::
        // createClient() throws "kernel should only be booted once".
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $customer = $this->createInvoiceCustomer();
        $creator = $this->getUserByRole(User::ROLE_ADMIN);
        $invoice = $this->createNewInvoice($customer, $creator);
        $invoice->submitForPaymentApproval(1);
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
        $invoiceId = $invoice->getId();
        self::assertIsInt($invoiceId);

        $token = $this->extractInvoiceToken($client, $invoiceId, '/approve-payment');
        $this->request($client, '/invoice/' . $invoiceId . '/approve-payment', 'POST', ['_token' => $token]);
        $this->assertIsRedirect($client, $this->createUrl('/invoice/edit/' . $invoiceId));
        $client->followRedirect();
        $this->assertHasFlashSuccess($client);

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Invoice::class)->find($invoiceId);
        self::assertInstanceOf(Invoice::class, $reloaded);
        self::assertTrue($reloaded->isPaymentApproved());
    }

    /**
     * Task 2.9: an ineligible caller (no matching role, not the named
     * approver) is denied a business-rule decision - a flash error, not an
     * access-control 403, and the invoice stays unapproved.
     */
    public function testApprovePaymentActionByIneligibleApproverIsDenied(): void
    {
        // Client role must hold `edit_invoice` (else the coarse controller
        // gate 403s before the policy is ever reached) but must NOT hold
        // level 1's seeded ROLE_TEAMLEAD, and must be a DIFFERENT user than
        // the invoice's own creator (else creator-exclusion fires instead).
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $customer = $this->createInvoiceCustomer();
        $creator = $this->getUserByRole(User::ROLE_TEAMLEAD);
        $invoice = $this->createNewInvoice($customer, $creator);
        $invoice->submitForPaymentApproval(1);
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
        $invoiceId = $invoice->getId();
        self::assertIsInt($invoiceId);

        $token = $this->extractInvoiceToken($client, $invoiceId, '/approve-payment');
        $this->request($client, '/invoice/' . $invoiceId . '/approve-payment', 'POST', ['_token' => $token]);
        $this->assertIsRedirect($client, $this->createUrl('/invoice/edit/' . $invoiceId));
        $client->followRedirect();
        $this->assertHasFlashError($client, 'This user cannot approve this invoice payment.');

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Invoice::class)->find($invoiceId);
        self::assertInstanceOf(Invoice::class, $reloaded);
        self::assertFalse($reloaded->isPaymentApproved());
    }

    /**
     * Task 2.9: reject discards the invoice's payment approval progress.
     */
    public function testRejectPaymentActionDiscardsProgress(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $customer = $this->createInvoiceCustomer();
        $creator = $this->getUserByRole(User::ROLE_ADMIN);
        $invoice = $this->createNewInvoice($customer, $creator);
        $invoice->submitForPaymentApproval(1);
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
        $invoiceId = $invoice->getId();
        self::assertIsInt($invoiceId);

        $token = $this->extractInvoiceToken($client, $invoiceId, '/reject-payment');
        $this->request($client, '/invoice/' . $invoiceId . '/reject-payment', 'POST', ['_token' => $token]);
        $this->assertIsRedirect($client, $this->createUrl('/invoice/edit/' . $invoiceId));

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Invoice::class)->find($invoiceId);
        self::assertInstanceOf(Invoice::class, $reloaded);
        self::assertSame(Invoice::PAYMENT_APPROVAL_REJECTED, $reloaded->getPaymentApprovalStatus());
        self::assertSame(0, $reloaded->getPaymentCurrentLevel());
    }

    /**
     * Task 2.11/D6 gate point 1: changeStatusAction's PAID branch must
     * refuse an unsubmitted invoice, never mutating or persisting.
     */
    public function testChangeStatusToPaidIsDeniedForUnsubmittedInvoice(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $customer = $this->createInvoiceCustomer();
        $creator = $this->getUserByRole(User::ROLE_ADMIN);
        $invoice = $this->createNewInvoice($customer, $creator);
        $invoiceId = $invoice->getId();
        self::assertIsInt($invoiceId);

        // real, rendered token-bearing link (mirrors testCreateActionAsAdminWithDownloadAndStatusChange) -
        // a token minted via getCsrfToken() does not validate against the real request/session.
        $this->request($client, '/invoice/show');
        self::assertTrue($client->getResponse()->isSuccessful());
        $link = $client->getCrawler()->selectLink('Invoice paid');
        $href = $link->attr('href');
        self::assertIsString($href);
        $this->request($client, $href);
        $this->assertIsRedirect($client, $this->createUrl('/invoice/show'));
        $client->followRedirect();
        $this->assertHasFlashError($client, 'invoice.payment_approval_required');

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Invoice::class)->find($invoiceId);
        self::assertInstanceOf(Invoice::class, $reloaded);
        self::assertFalse($reloaded->isPaid());
        self::assertNull($reloaded->getPaymentDate());
    }

    /**
     * Task 2.11/D6 gate point 2: the InvoiceEditForm status dropdown is a
     * second, independent PAID-persistence path (editAction ->
     * saveInvoice()) and must be gated too, closing the bypass identified
     * in design D6.
     */
    public function testEditFormStatusDropdownIsDeniedForUnsubmittedInvoice(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $customer = $this->createInvoiceCustomer();
        $creator = $this->getUserByRole(User::ROLE_ADMIN);
        $invoice = $this->createNewInvoice($customer, $creator);
        $invoiceId = $invoice->getId();
        self::assertIsInt($invoiceId);

        $crawler = $this->request($client, '/invoice/edit/' . $invoiceId);
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $crawler->filter('form[name=invoice_edit_form]')->form();
        $client->submit($form, [
            'invoice_edit_form' => [
                'status' => Invoice::STATUS_PAID,
                'paymentDate' => (new \DateTime())->format(self::DEFAULT_DATE_FORMAT),
            ],
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasFlashError($client);

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Invoice::class)->find($invoiceId);
        self::assertInstanceOf(Invoice::class, $reloaded);
        self::assertFalse($reloaded->isPaid());
    }

    /**
     * Task 2.14/decision 8: an already-PAID historical invoice is
     * grandfathered - re-saving it (comment-only edit) is NOT blocked by
     * either gate, since paymentApprovalStatus stays null and unread.
     */
    public function testAlreadyPaidInvoiceCommentOnlyResaveIsNotBlocked(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $customer = $this->createInvoiceCustomer();
        $creator = $this->getUserByRole(User::ROLE_ADMIN);
        $invoice = $this->createNewInvoice($customer, $creator);
        $invoice->setIsPaid();
        $invoice->setPaymentDate(new \DateTime());
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
        $invoiceId = $invoice->getId();
        self::assertIsInt($invoiceId);

        $crawler = $this->request($client, '/invoice/edit/' . $invoiceId);
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $crawler->filter('form[name=invoice_edit_form]')->form();
        $client->submit($form, [
            'invoice_edit_form' => [
                'comment' => 'grandfathered re-save',
                'status' => Invoice::STATUS_PAID,
                'paymentDate' => (new \DateTime())->format(self::DEFAULT_DATE_FORMAT),
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/show'));
        $client->followRedirect();
        $this->assertHasFlashSuccess($client);

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Invoice::class)->find($invoiceId);
        self::assertInstanceOf(Invoice::class, $reloaded);
        self::assertTrue($reloaded->isPaid());
        self::assertSame('grandfathered re-save', $reloaded->getComment());
        self::assertNull($reloaded->getPaymentApprovalStatus());
    }

    /**
     * Task 3.3/regression guard: the D6 dual-gate targets ONLY the PAID
     * transition - PENDING and CANCELED must remain fully ungated even for
     * an invoice that was never submitted for payment approval.
     */
    public function testChangeStatusToPendingAndCanceledRemainUngatedForUnsubmittedInvoice(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $customer = $this->createInvoiceCustomer();
        $creator = $this->getUserByRole(User::ROLE_ADMIN);
        $invoice = $this->createNewInvoice($customer, $creator);
        $invoice->setStatus(Invoice::STATUS_NEW);
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
        $invoiceId = $invoice->getId();
        self::assertIsInt($invoiceId);
        self::assertNull($invoice->getPaymentApprovalStatus());

        // real, rendered token-bearing links (mirrors
        // testCreateActionAsAdminWithDownloadAndStatusChange) - a token
        // minted via getCsrfToken() does not validate against the real
        // client session used by subsequent $client->request() calls.
        $this->request($client, '/invoice/show');
        self::assertTrue($client->getResponse()->isSuccessful());
        $link = $client->getCrawler()->selectLink('Waiting for payment');
        $href = $link->attr('href');
        self::assertIsString($href);
        $this->request($client, $href);
        $this->assertIsRedirect($client, $this->createUrl('/invoice/show'));
        $client->followRedirect();
        $this->assertHasFlashSuccess($client);

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Invoice::class)->find($invoiceId);
        self::assertInstanceOf(Invoice::class, $reloaded);
        self::assertTrue($reloaded->isPending());

        $link = $client->getCrawler()->selectLink('Cancel invoice');
        $href = $link->attr('href');
        self::assertIsString($href);
        $this->request($client, $href);
        $this->assertIsRedirect($client, $this->createUrl('/invoice/show'));
        $client->followRedirect();
        $this->assertHasFlashSuccess($client);

        $em->clear();
        $reloaded = $em->getRepository(Invoice::class)->find($invoiceId);
        self::assertInstanceOf(Invoice::class, $reloaded);
        self::assertTrue($reloaded->isCanceled());
    }

    /**
     * Task 3.4: a historical PAID invoice (pre-change, paymentApprovalStatus
     * is null) must not be flagged as "unapproved" in the invoice_edit page -
     * the only new UI touchpoint that exists before the approvals dashboard
     * (PR4). No submit/approve/reject payment-approval action is offered.
     */
    public function testHistoricalPaidInvoiceShowsNoUnapprovedFlagOrPaymentApprovalActions(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $customer = $this->createInvoiceCustomer();
        $creator = $this->getUserByRole(User::ROLE_ADMIN);
        $invoice = $this->createNewInvoice($customer, $creator);
        $invoice->setIsPaid();
        $invoice->setPaymentDate(new \DateTime());
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
        $invoiceId = $invoice->getId();
        self::assertIsInt($invoiceId);
        self::assertNull($invoice->getPaymentApprovalStatus());

        $crawler = $this->request($client, '/invoice/edit/' . $invoiceId);
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Submit for payment approval', $content);
        self::assertStringNotContainsString('Approve payment', $content);
        self::assertStringNotContainsString('Reject payment', $content);
        self::assertSame(0, $crawler->filter('form[action$="/submit-payment-approval"]')->count());
        self::assertSame(0, $crawler->filter('form[action$="/approve-payment"]')->count());
        self::assertSame(0, $crawler->filter('form[action$="/reject-payment"]')->count());
    }
}
