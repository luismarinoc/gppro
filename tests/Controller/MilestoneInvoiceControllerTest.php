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
use App\Entity\Invoice;
use App\Entity\InvoiceTemplate;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\Team;
use App\Entity\Timesheet;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class MilestoneInvoiceControllerTest extends AbstractControllerBaseTestCase
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

    private function createCustomer(): Customer
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Milestone invoice controller test customer ' . uniqid());
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
        $project->setName('Milestone invoice controller test project ' . uniqid());
        $project->setCustomer($customer);
        $em->persist($project);
        $em->flush();

        return $project;
    }

    private function createMilestone(Project $project, string $name, string $value = '1000.0000', string $currency = 'CLP'): Milestone
    {
        $em = $this->getEntityManager();

        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName($name . ' ' . uniqid());
        $milestone->setValue($value);
        $milestone->setCurrency($currency);
        $em->persist($milestone);
        $em->flush();

        return $milestone;
    }

    private function addBillableHours(Milestone $milestone, User $user): void
    {
        $em = $this->getEntityManager();
        $project = $milestone->getProject();
        self::assertNotNull($project);

        $activity = new Activity();
        $activity->setName('Billable activity ' . uniqid());
        $activity->setProject($project);
        $activity->setMilestone($milestone);
        $em->persist($activity);
        $em->flush();

        $timesheet = new Timesheet();
        $timesheet->setProject($project);
        $timesheet->setActivity($activity);
        $timesheet->setUser($user);
        $timesheet->setBegin(new \DateTime('-2 hour'));
        $timesheet->setEnd(new \DateTime('-1 hour'));
        $timesheet->setDuration(3600);
        $timesheet->setBillable(true);
        $em->persist($timesheet);

        $em->flush();
    }

    private function nameOf(Milestone $milestone): string
    {
        $name = $milestone->getName();
        self::assertNotNull($name);

        return $name;
    }

    private function createCompatibleTemplate(): InvoiceTemplate
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Milestone invoice controller test template customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $suffix = uniqid();
        $template = new InvoiceTemplate();
        $template->setName('Milestone invoice template ' . $suffix);
        $template->setTitle('Milestone invoice template ' . $suffix);
        $template->setCustomer($customer);
        $template->setCalculator('default');
        $template->setRenderer('invoice');
        $template->setLanguage('en');
        $em->persist($template);
        $em->flush();

        return $template;
    }

    public function testIsSecure(): void
    {
        // no entity manager access before this call: assertUrlIsSecured()
        // boots its own kernel/client and a second boot is not supported
        $this->assertUrlIsSecured('/invoice/milestones/1');
    }

    public function testIsSecureForRole(): void
    {
        // ROLE_USER lacks view_invoice/create_invoice, denied before any
        // Customer with id=1 needs to actually exist
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/invoice/milestones/1');
    }

    public function testIndexActionListsOnlyInvoiceableAndConvertibleMilestones(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $customer = $this->createCustomer();
        $project = $this->createProject($customer);

        $invoiceable = $this->createMilestone($project, 'Invoiceable CLP');

        // not convertible: USD with no FxRate rows present in the test DB
        $notConvertible = $this->createMilestone($project, 'Not convertible USD', '500.0000', 'USD');

        // already invoiced
        $alreadyInvoiced = $this->createMilestone($project, 'Already invoiced');
        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->setUser($this->getUserByRole(User::ROLE_TEAMLEAD));
        $invoice->setInvoiceNumber('INV-' . uniqid());
        $invoice->setFilename('invoice-' . uniqid());
        $invoice->setCreatedAt(new \DateTime());
        $invoice->setCurrency('CLP');
        $invoice->setTotal(1000.00);
        $invoice->setVat(0.0);
        $invoice->setTax(0.0);
        $invoice->setDueDays(30);
        $em = $this->getEntityManager();
        $em->persist($invoice);
        $em->flush();
        $alreadyInvoiced->setInvoice($invoice);
        $em->persist($alreadyInvoiced);
        $em->flush();

        $this->request($client, '/invoice/milestones/' . $customer->getId());
        self::assertTrue($client->getResponse()->isSuccessful());

        $html = $client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString($this->nameOf($invoiceable), $html);
        self::assertStringNotContainsString($this->nameOf($notConvertible), $html);
        self::assertStringNotContainsString($this->nameOf($alreadyInvoiced), $html);
    }

    public function testIndexActionShowsWarningAndDisablesCheckboxForMilestoneWithoutBillableHours(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $customer = $this->createCustomer();
        $project = $this->createProject($customer);

        $withoutHours = $this->createMilestone($project, 'No hours yet');
        $withHours = $this->createMilestone($project, 'Has hours');
        $this->addBillableHours($withHours, $this->getUserByRole(User::ROLE_TEAMLEAD));

        $this->request($client, '/invoice/milestones/' . $customer->getId());
        self::assertTrue($client->getResponse()->isSuccessful());

        $crawler = $client->getCrawler();
        $rows = $crawler->filter('table.dataTable tbody tr');

        $withoutHoursRow = null;
        $withHoursRow = null;
        foreach ($rows as $row) {
            $rowCrawler = new \Symfony\Component\DomCrawler\Crawler($row);
            $text = $rowCrawler->text();
            if (str_contains($text, $this->nameOf($withoutHours))) {
                $withoutHoursRow = $rowCrawler;
            } elseif (str_contains($text, $this->nameOf($withHours))) {
                $withHoursRow = $rowCrawler;
            }
        }

        self::assertNotNull($withoutHoursRow, 'milestone without billable hours must still be listed');
        self::assertNotNull($withHoursRow);

        self::assertEquals(1, $withoutHoursRow->filter('.milestone_no_hours_warning')->count());
        self::assertEquals(1, $withoutHoursRow->filter('input[type=checkbox][disabled]')->count());

        self::assertEquals(0, $withHoursRow->filter('.milestone_no_hours_warning')->count());
        self::assertEquals(0, $withHoursRow->filter('input[type=checkbox][disabled]')->count());
    }

    public function testCreateActionRejectsMilestoneWithoutBillableHours(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $customer = $this->createCustomer();
        $project = $this->createProject($customer);
        $milestone = $this->createMilestone($project, 'No hours logged yet');

        $template = $this->createCompatibleTemplate();

        $this->request($client, '/invoice/milestones/' . $customer->getId());
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=multi_update_table]')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/milestones/' . $customer->getId() . '/create'));

        $client->submit($form, [
            'multi_update_table' => [
                'entities' => (string) $milestone->getId(),
                'template' => $template->getId(),
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/milestones/' . $customer->getId()));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasFlashError($client);

        $em = $this->getEntityManager();
        $em->clear();

        self::assertCount(0, $em->getRepository(Invoice::class)->findBy(['customer' => $customer->getId()]));

        /** @var Milestone $reloaded */
        $reloaded = $em->getRepository(Milestone::class)->find($milestone->getId());
        self::assertFalse($reloaded->isInvoiced());
    }

    public function testPickCustomerActionIsSecure(): void
    {
        $this->assertUrlIsSecured('/invoice/milestones/');
    }

    public function testPickCustomerActionListsOnlyCustomersWithInvoiceableMilestones(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $customerWithMilestone = $this->createCustomer();
        $projectWithMilestone = $this->createProject($customerWithMilestone);
        $this->createMilestone($projectWithMilestone, 'Invoiceable');

        $customerWithoutMilestone = $this->createCustomer();

        $this->request($client, '/invoice/milestones/');
        self::assertTrue($client->getResponse()->isSuccessful());

        // scope the assertion to this screen's table, not the whole page:
        // the global layout (e.g. a quick customer switcher) may legitimately
        // list every customer's name elsewhere on the page.
        $table = $client->getCrawler()->filter('table.dataTable')->html();
        $nameWithMilestone = $customerWithMilestone->getName();
        $nameWithoutMilestone = $customerWithoutMilestone->getName();
        self::assertNotNull($nameWithMilestone);
        self::assertNotNull($nameWithoutMilestone);
        self::assertStringContainsString($nameWithMilestone, $table);
        self::assertStringNotContainsString($nameWithoutMilestone, $table);
    }

    public function testPickCustomerActionShowsEmptyStateWhenNoCustomerHasInvoiceableMilestones(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $this->request($client, '/invoice/milestones/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $html = $client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringContainsString('No customer currently has milestones pending invoicing.', $html);
    }

    public function testCreateActionHappyPathGeneratesInvoiceAndMarksMilestonesInvoiced(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $customer = $this->createCustomer();
        $projectA = $this->createProject($customer);
        $projectB = $this->createProject($customer);

        $milestoneA = $this->createMilestone($projectA, 'Milestone A');
        $milestoneB = $this->createMilestone($projectB, 'Milestone B');
        $user = $this->getUserByRole(User::ROLE_TEAMLEAD);
        $this->addBillableHours($milestoneA, $user);
        $this->addBillableHours($milestoneB, $user);

        $template = $this->createCompatibleTemplate();

        $this->request($client, '/invoice/milestones/' . $customer->getId());
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=multi_update_table]')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/milestones/' . $customer->getId() . '/create'));

        $client->submit($form, [
            'multi_update_table' => [
                'entities' => $milestoneA->getId() . ',' . $milestoneB->getId(),
                'template' => $template->getId(),
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/milestones/' . $customer->getId()));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasFlashSuccess($client);

        $em = $this->getEntityManager();
        $em->clear();

        $invoices = $em->getRepository(Invoice::class)->findBy(['customer' => $customer->getId()]);
        self::assertCount(1, $invoices);

        /** @var Milestone $reloadedA */
        $reloadedA = $em->getRepository(Milestone::class)->find($milestoneA->getId());
        /** @var Milestone $reloadedB */
        $reloadedB = $em->getRepository(Milestone::class)->find($milestoneB->getId());

        self::assertTrue($reloadedA->isInvoiced());
        self::assertTrue($reloadedB->isInvoiced());
        self::assertSame($invoices[0]->getId(), $reloadedA->getInvoice()?->getId());
        self::assertSame($invoices[0]->getId(), $reloadedB->getInvoice()?->getId());

        // no longer offered in the selector
        $this->request($client, '/invoice/milestones/' . $customer->getId());
        self::assertTrue($client->getResponse()->isSuccessful());
        $html = $client->getResponse()->getContent();
        self::assertIsString($html);
        self::assertStringNotContainsString($this->nameOf($reloadedA), $html);
        self::assertStringNotContainsString($this->nameOf($reloadedB), $html);
    }

    public function testCreateActionRejectsMixedCustomerSelection(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $customerA = $this->createCustomer();
        $customerB = $this->createCustomer();
        $projectA = $this->createProject($customerA);
        $projectB = $this->createProject($customerB);

        $milestoneA = $this->createMilestone($projectA, 'Customer A milestone');
        $milestoneB = $this->createMilestone($projectB, 'Customer B milestone');

        $template = $this->createCompatibleTemplate();

        $this->request($client, '/invoice/milestones/' . $customerA->getId());
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=multi_update_table]')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/milestones/' . $customerA->getId() . '/create'));

        $client->submit($form, [
            'multi_update_table' => [
                'entities' => $milestoneA->getId() . ',' . $milestoneB->getId(),
                'template' => $template->getId(),
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/milestones/' . $customerA->getId()));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasFlashError($client);

        $em = $this->getEntityManager();
        $em->clear();

        self::assertCount(0, $em->getRepository(Invoice::class)->findAll());

        /** @var Milestone $reloadedA */
        $reloadedA = $em->getRepository(Milestone::class)->find($milestoneA->getId());
        /** @var Milestone $reloadedB */
        $reloadedB = $em->getRepository(Milestone::class)->find($milestoneB->getId());
        self::assertFalse($reloadedA->isInvoiced());
        self::assertFalse($reloadedB->isInvoiced());
    }

    public function testCreateActionRevalidatesConvertibilityAtGenerationTimeAndRejectsStaleSelection(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $customer = $this->createCustomer();
        $project = $this->createProject($customer);

        $validMilestone = $this->createMilestone($project, 'Valid CLP milestone');
        $this->addBillableHours($validMilestone, $this->getUserByRole(User::ROLE_TEAMLEAD));
        // never convertible: USD with no FxRate rows present in the test DB,
        // simulating an ID that went stale (or was tampered) between listing
        // and this submit
        $staleMilestone = $this->createMilestone($project, 'Stale USD milestone', '500.0000', 'USD');

        $template = $this->createCompatibleTemplate();

        $this->request($client, '/invoice/milestones/' . $customer->getId());
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=multi_update_table]')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/milestones/' . $customer->getId() . '/create'));

        $client->submit($form, [
            'multi_update_table' => [
                'entities' => $validMilestone->getId() . ',' . $staleMilestone->getId(),
                'template' => $template->getId(),
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/milestones/' . $customer->getId()));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasFlashError($client);

        $em = $this->getEntityManager();
        $em->clear();

        self::assertCount(0, $em->getRepository(Invoice::class)->findBy(['customer' => $customer->getId()]));

        /** @var Milestone $reloadedValid */
        $reloadedValid = $em->getRepository(Milestone::class)->find($validMilestone->getId());
        self::assertFalse($reloadedValid->isInvoiced());
    }

    public function testCreateActionDropsInvalidMilestoneIdFromCsvWithoutBlockingRealOnes(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $customer = $this->createCustomer();
        $project = $this->createProject($customer);
        $milestone = $this->createMilestone($project, 'Real milestone');
        $this->addBillableHours($milestone, $this->getUserByRole(User::ROLE_TEAMLEAD));

        $template = $this->createCompatibleTemplate();

        $this->request($client, '/invoice/milestones/' . $customer->getId());
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=multi_update_table]')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/milestones/' . $customer->getId() . '/create'));

        // a bogus, never-persisted ID mixed into the CSV
        $bogusId = 999999999;

        $client->submit($form, [
            'multi_update_table' => [
                'entities' => $milestone->getId() . ',' . $bogusId,
                'template' => $template->getId(),
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/milestones/' . $customer->getId()));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasFlashSuccess($client);

        $em = $this->getEntityManager();
        $em->clear();

        $invoices = $em->getRepository(Invoice::class)->findBy(['customer' => $customer->getId()]);
        self::assertCount(1, $invoices);

        /** @var Milestone $reloaded */
        $reloaded = $em->getRepository(Milestone::class)->find($milestone->getId());
        self::assertTrue($reloaded->isInvoiced());
    }

    public function testCreateActionRejectsForeignCustomerMilestoneOutsideTeamAccessIdor(): void
    {
        // client must be created first: booting a second kernel via the
        // entity manager afterwards is not supported by WebTestCase (see
        // testCreateActionRequiresCreateInvoicePermission above)
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        // attacker: team-scoped (canSeeAllData() === false for ROLE_TEAMLEAD,
        // see config/packages/gppro.yaml) user, explicitly restricted via
        // team membership to Customer A ONLY. This is a realistic,
        // legitimate non-admin role in this app.
        $attacker = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $customerA = $this->createCustomer();
        $customerB = $this->createCustomer();

        $em = $this->getEntityManager();

        // Customer A: attacker IS on this team -> #[IsGranted('access', 'customer')]
        // on the route legitimately passes for A.
        $teamA = new Team('Team A ' . uniqid());
        $teamA->addUser($attacker);
        $teamA->addCustomer($customerA);
        $em->persist($teamA);

        // Customer B: has a team the attacker is NOT a member of -> access
        // to B must be denied (checkTeamAccessCustomer fails-open only when
        // a customer has ZERO teams; B is deliberately given one to prove
        // this is a real, enforced restriction, not an accidental default-allow).
        $teamB = new Team('Team B ' . uniqid());
        $teamB->addCustomer($customerB);
        $em->persist($teamB);
        $em->flush();

        // the attacker also has a legitimate milestone of their own on A —
        // needed only so the listing page actually renders the
        // multi_update_table form/CSRF token; the exploit tampers the
        // submitted CSV to reference a foreign milestone instead
        $projectA = $this->createProject($customerA);
        $this->createMilestone($projectA, 'Customer A own milestone');

        $projectB = $this->createProject($customerB);
        // Milestone IDs are plain auto-increment integers, trivially
        // enumerable — no leak of B's id is required for this exploit.
        $foreignMilestone = $this->createMilestone($projectB, 'Customer B milestone (foreign)');
        $this->addBillableHours($foreignMilestone, $attacker);

        $template = $this->createCompatibleTemplate();

        // Step 1: the attacker legitimately loads the listing for Customer A
        // (their own, authorized customer) to obtain a valid CSRF token.
        $this->request($client, '/invoice/milestones/' . $customerA->getId());
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=multi_update_table]')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/milestones/' . $customerA->getId() . '/create'));

        // Step 2: POST against the AUTHORIZED route (Customer A) but with a
        // SINGLE milestone id belonging to Customer B. Not "mixed" (only one
        // customer in the resolved selection), so the existing mixed-customer
        // check alone does not catch it.
        $client->submit($form, [
            'multi_update_table' => [
                'entities' => (string) $foreignMilestone->getId(),
                'template' => $template->getId(),
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/milestones/' . $customerA->getId()));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());

        // Must be rejected with a clear error, exactly like the existing
        // mixed-customer / stale-selection validations in this controller —
        // never a success flash.
        $this->assertHasFlashError($client);

        $em->clear();

        // No invoice may ever be created for Customer B (or anyone) as a
        // side effect of this request.
        self::assertCount(0, $em->getRepository(Invoice::class)->findBy(['customer' => $customerB->getId()]));

        /** @var Milestone $reloadedForeign */
        $reloadedForeign = $em->getRepository(Milestone::class)->find($foreignMilestone->getId());
        self::assertFalse($reloadedForeign->isInvoiced());
    }

    public function testCreateActionRequiresCreateInvoicePermission(): void
    {
        // client must be created first: booting a second kernel via the
        // entity manager afterwards is not supported by WebTestCase
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);

        $customer = $this->createCustomer();
        $project = $this->createProject($customer);
        $milestone = $this->createMilestone($project, 'No permission milestone');
        $template = $this->createCompatibleTemplate();

        $client->request('POST', $this->createUrl('/invoice/milestones/' . $customer->getId() . '/create'), [
            'multi_update_table' => [
                'entities' => (string) $milestone->getId(),
                'template' => $template->getId(),
            ],
        ]);

        self::assertFalse($client->getResponse()->isSuccessful());
        $this->assertAccessDenied($client);
    }
}
