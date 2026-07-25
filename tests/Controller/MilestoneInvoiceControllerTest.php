<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\InvoiceTemplate;
use App\Entity\Milestone;
use App\Entity\Project;
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

    public function testCreateActionHappyPathGeneratesInvoiceAndMarksMilestonesInvoiced(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $customer = $this->createCustomer();
        $projectA = $this->createProject($customer);
        $projectB = $this->createProject($customer);

        $milestoneA = $this->createMilestone($projectA, 'Milestone A');
        $milestoneB = $this->createMilestone($projectB, 'Milestone B');

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
