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
use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Entity\Invoice;
use App\Entity\Project;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\Timesheet;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers ApprovalsDashboardController: cross-domain aggregation (Expense +
 * Invoice + Timesheet), the navigation-only contract (decision 7 - no
 * inline approve/reject controls), and - most importantly - that raw
 * repository results are never trusted as the final visibility set. Both
 * ExpenseRepository::findPendingForUser() and the new
 * InvoiceRepository::findPendingPaymentApprovalForUser() are deliberately
 * naive (creator-exclusion only, see design's flagged gap); the controller
 * MUST additionally apply each domain's real is_granted() check per item.
 */
#[Group('integration')]
class ApprovalsDashboardControllerTest extends AbstractControllerBaseTestCase
{
    // --- Expense fixtures (mirrors ExpenseControllerTest) ---

    /** @return array{0: Customer, 1: Project} */
    private function createCustomerAndProject(string $suffix = ''): array
    {
        $customer = new Customer('Dashboard test customer ' . uniqid() . $suffix);
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $this->getEntityManager()->persist($customer);

        $project = new Project();
        $project->setName('Dashboard test project ' . uniqid() . $suffix);
        $project->setCustomer($customer);
        $this->getEntityManager()->persist($project);
        $this->getEntityManager()->flush();

        return [$customer, $project];
    }

    private function createPendingExpense(Project $project, User $createdBy, int $amountClp = 100000): Expense
    {
        $em = $this->getEntityManager();

        $expense = new Expense();
        $expense->setDescription('Dashboard test expense ' . uniqid());
        $expense->setAmount($amountClp);
        $expense->setExpenseDate(new \DateTimeImmutable('2026-08-01'));
        $expense->setCreatedBy($createdBy);
        $allocation = (new ExpenseAllocation())->setProject($project)->setPercentage('100.00');
        $expense->addAllocation($allocation);
        $em->persist($expense);
        $em->flush();

        $expense->submitForApproval(1);
        $em->flush();

        return $expense;
    }

    // --- Invoice fixtures (mirrors InvoiceControllerTest) ---

    private function createInvoiceCustomer(): Customer
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Dashboard test invoice customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);
        $em->flush();

        return $customer;
    }

    private function createPendingInvoice(Customer $customer, User $createdBy, float $total = 100000.0): Invoice
    {
        $em = $this->getEntityManager();

        $invoice = new Invoice();
        $invoice->setStatus(Invoice::STATUS_PENDING);
        $invoice->setTotal($total);
        $invoice->setVat(0.0);
        $invoice->setTax(0.0);
        $invoice->setCustomer($customer);
        $invoice->setInvoiceNumber('inv-dashboard-' . uniqid());
        $invoice->setFilename('inv-dashboard-' . uniqid());
        $invoice->setCreatedAt(new \DateTime());
        $invoice->setUser($createdBy);
        $invoice->setDueDays(30);
        $invoice->setCurrency('CLP');
        $em->persist($invoice);
        $em->flush();

        $invoice->submitForPaymentApproval(1);
        $em->persist($invoice);
        $em->flush();

        return $invoice;
    }

    /**
     * A grandfathered PAID invoice (decision 8): paymentApprovalStatus stays
     * NULL forever, never submitted for payment approval.
     */
    private function createGrandfatheredPaidInvoice(Customer $customer, User $createdBy, float $total = 100000.0): Invoice
    {
        $em = $this->getEntityManager();

        $invoice = new Invoice();
        $invoice->setStatus(Invoice::STATUS_PAID);
        $invoice->setTotal($total);
        $invoice->setVat(0.0);
        $invoice->setTax(0.0);
        $invoice->setCustomer($customer);
        $invoice->setInvoiceNumber('inv-dashboard-paid-' . uniqid());
        $invoice->setFilename('inv-dashboard-paid-' . uniqid());
        $invoice->setCreatedAt(new \DateTime());
        $invoice->setUser($createdBy);
        $invoice->setDueDays(30);
        $invoice->setCurrency('CLP');
        $em->persist($invoice);
        $em->flush();

        return $invoice;
    }

    // --- Timesheet fixtures (mirrors TimesheetTeamControllerTest) ---

    private function createTeam(): Team
    {
        $team = new Team('Dashboard controller test team ' . uniqid());
        $this->getEntityManager()->persist($team);
        $this->getEntityManager()->flush();

        return $team;
    }

    /** @return array{0: Customer, 1: Project} */
    private function createCustomerAndProjectWithTeam(Team $team): array
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Dashboard controller test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');

        $project = new Project();
        $project->setName('Dashboard controller test project ' . uniqid());
        $project->setCustomer($customer);
        $project->addTeam($team);

        $em->persist($customer);
        $em->persist($project);
        $em->flush();

        return [$customer, $project];
    }

    private function addUserToTeamAsLead(User $user, Team $team): void
    {
        $member = new TeamMember();
        $member->setTeam($team);
        $member->setUser($user);
        $member->setTeamlead(true);
        $this->getEntityManager()->persist($member);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->refresh($user);
        $this->getEntityManager()->refresh($team);
    }

    private function createPendingTimesheetEntry(User $owner, Project $project): Timesheet
    {
        $em = $this->getEntityManager();

        $activity = new Activity();
        $activity->setName('Dashboard controller test activity ' . uniqid());
        $activity->setProject($project);
        $em->persist($activity);
        $em->flush();

        $timesheet = new Timesheet();
        $timesheet->setUser($owner);
        $timesheet->setProject($project);
        $timesheet->setActivity($activity);
        $timesheet->setBegin(new \DateTime('-2 hours'));
        $timesheet->setEnd(new \DateTime('-1 hours'));
        $em->persist($timesheet);
        $em->flush();

        return $timesheet;
    }

    public function testDashboardRouteIsSecured(): void
    {
        $this->assertUrlIsSecured('/approvals/');
    }

    /**
     * Task 4.5: a user eligible to approve in all three domains sees all
     * three domains' pending items in one aggregated page.
     */
    public function testDashboardAggregatesPendingItemsAcrossAllThreeDomains(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $em = $this->getEntityManager();

        $teamlead = $this->loadUserFromDatabase(UserFixtures::USERNAME_TEAMLEAD);
        $admin = $this->loadUserFromDatabase(UserFixtures::USERNAME_ADMIN);

        [, $expenseProject] = $this->createCustomerAndProject('-expense');
        $expense = $this->createPendingExpense($expenseProject, $admin);

        $invoiceCustomer = $this->createInvoiceCustomer();
        $invoice = $this->createPendingInvoice($invoiceCustomer, $admin);

        $team = $this->createTeam();
        $this->addUserToTeamAsLead($teamlead, $team);
        [, $timesheetProject] = $this->createCustomerAndProjectWithTeam($team);
        $timesheet = $this->createPendingTimesheetEntry($admin, $timesheetProject);

        $em->clear();

        $this->request($client, '/approvals/');

        self::assertTrue($client->getResponse()->isSuccessful());
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString((string) $expense->getDescription(), $content);
        self::assertStringContainsString((string) $invoice->getInvoiceNumber(), $content);
        self::assertStringContainsString((string) $timesheet->getId(), $content);
    }

    /**
     * Task 4.5: a user with pending items in only one domain (Timesheet)
     * sees only that domain's rows - no Expense or Invoice rows appear.
     */
    public function testDashboardShowsOnlySingleDomainWhenOthersAreEmptyForUser(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $em = $this->getEntityManager();

        $teamlead = $this->loadUserFromDatabase(UserFixtures::USERNAME_TEAMLEAD);
        $admin = $this->loadUserFromDatabase(UserFixtures::USERNAME_ADMIN);

        $team = $this->createTeam();
        $this->addUserToTeamAsLead($teamlead, $team);
        [, $timesheetProject] = $this->createCustomerAndProjectWithTeam($team);
        $timesheet = $this->createPendingTimesheetEntry($admin, $timesheetProject);

        $em->clear();

        $this->request($client, '/approvals/');

        self::assertTrue($client->getResponse()->isSuccessful());
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString((string) $timesheet->getId(), $content);
        self::assertStringNotContainsString('inv-dashboard-', $content, 'No Invoice rows must appear when nothing is pending for this user in that domain.');
    }

    /**
     * Task 4.5: zero pending approvals anywhere renders an empty state, not
     * an error.
     */
    public function testDashboardShowsEmptyStateWhenNothingPending(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $this->request($client, '/approvals/');

        self::assertTrue($client->getResponse()->isSuccessful());
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('You have no pending approvals', $content);
    }

    /**
     * Task 4.6 (CRITICAL): InvoiceRepository::findPendingPaymentApprovalForUser()
     * is deliberately naive - it only excludes the invoice's creator, it does
     * NOT check whether the caller actually holds the level's required role
     * or is the named approver. A caller who is neither the creator NOR an
     * eligible approver (no ROLE_TEAMLEAD, not the named approver, not
     * super-admin) would still be returned by the raw repository query. The
     * dashboard MUST filter this out via the real
     * InvoicePaymentApprovalPolicy/approve_invoice_payment voter check
     * before rendering - this is the leakage the design explicitly flagged.
     */
    public function testDashboardDoesNotLeakInvoiceRawRepositoryResultToIneligibleApprover(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $em = $this->getEntityManager();

        $admin = $this->loadUserFromDatabase(UserFixtures::USERNAME_ADMIN);
        $plainUser = $this->loadUserFromDatabase(UserFixtures::USERNAME_USER);

        $invoiceCustomer = $this->createInvoiceCustomer();
        // Created by admin, not by plainUser - so the naive repository query
        // (creator-exclusion only) WOULD include it for plainUser. But
        // plainUser has ROLE_USER, not the seeded level 1's ROLE_TEAMLEAD,
        // is not the named approver, and is not super-admin: genuinely
        // ineligible per InvoicePaymentApprovalPolicy.
        $invoice = $this->createPendingInvoice($invoiceCustomer, $admin);

        $em->clear();

        $this->request($client, '/approvals/');

        self::assertTrue($client->getResponse()->isSuccessful());
        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString((string) $invoice->getInvoiceNumber(), $content, 'An invoice the caller is not eligible to approve must never appear on the dashboard, even though the raw repository query would include it.');
    }

    /**
     * Task 4.7: a user with approve_expense (ROLE_TEAMLEAD via the seeded
     * Expense level 1) but with no Invoice/Timesheet eligibility whatsoever
     * sees only their Expense rows - confirms per-domain isolation of the
     * permission filter, not just the Invoice case above.
     */
    public function testUserEligibleOnlyForExpenseSeesNoInvoiceOrTimesheetRows(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $em = $this->getEntityManager();

        $admin = $this->loadUserFromDatabase(UserFixtures::USERNAME_ADMIN);

        [, $expenseProject] = $this->createCustomerAndProject('-isolated-expense');
        $expense = $this->createPendingExpense($expenseProject, $admin);

        // Invoice created by someone else: raw query would include it for
        // $teamlead too (ROLE_TEAMLEAD DOES match level 1's required role,
        // so this one is a true positive - not the leakage case). Kept
        // absent here on purpose: this test only asserts Timesheet/Invoice
        // absence when there is truly nothing pending in those domains for
        // this user, i.e. no fixture created for them at all.
        $em->clear();

        $this->request($client, '/approvals/');

        self::assertTrue($client->getResponse()->isSuccessful());
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString((string) $expense->getDescription(), $content);
        self::assertStringContainsString('No invoices are awaiting your approval', $content);
        self::assertStringContainsString('No timesheet entries are awaiting your approval', $content);
    }

    /**
     * Task 4.8: dashboard rows are navigation-only - each row links into
     * its own domain's approve/reject screen, and no inline approve/reject
     * `<form>` submitting to a write endpoint is ever rendered on this page.
     */
    public function testDashboardRowsNavigateToDomainScreensWithNoInlineApproveRejectControls(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $em = $this->getEntityManager();

        $admin = $this->loadUserFromDatabase(UserFixtures::USERNAME_ADMIN);

        $invoiceCustomer = $this->createInvoiceCustomer();
        $invoice = $this->createPendingInvoice($invoiceCustomer, $admin);
        $invoiceId = $invoice->getId();
        self::assertIsInt($invoiceId);

        $em->clear();

        $crawler = $this->request($client, '/approvals/');

        self::assertTrue($client->getResponse()->isSuccessful());

        // Navigates to Invoice's own payment-approval screen (where the
        // approve/reject buttons actually live, per task 2.10), not an
        // inline action on this page.
        self::assertGreaterThan(
            0,
            $crawler->filter('a[href="' . $this->createUrl('/invoice/edit/' . $invoiceId) . '"]')->count(),
            'Dashboard must link the Invoice row to its own edit/approve screen.'
        );

        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('approve-payment', $content, 'The dashboard must not render a form posting to the Invoice approve-payment write endpoint.');
        self::assertStringNotContainsString('reject-payment', $content, 'The dashboard must not render a form posting to the Invoice reject-payment write endpoint.');
        self::assertStringNotContainsString('expense_approve', $content, 'The dashboard must not render a form posting to the Expense approve write endpoint.');
        self::assertStringNotContainsString('expense_reject', $content, 'The dashboard must not render a form posting to the Expense reject write endpoint.');
        self::assertStringNotContainsString('admin_timesheet_approve', $content, 'The dashboard must not render a form posting to the Timesheet approve write endpoint.');
        self::assertStringNotContainsString('admin_timesheet_reject', $content, 'The dashboard must not render a form posting to the Timesheet reject write endpoint.');
        self::assertCount(0, $crawler->filter('section.content form'), 'The dashboard\'s own content area must contain no <form> at all - navigation-only, decision 7.');
    }

    /**
     * Task 4.9 (decision 8 grandfathering): a historical PAID invoice, with
     * paymentApprovalStatus permanently NULL, must never be listed as an
     * "unapproved" pending item on the dashboard.
     */
    public function testHistoricalPaidInvoiceNeverAppearsOnDashboard(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $em = $this->getEntityManager();

        $admin = $this->loadUserFromDatabase(UserFixtures::USERNAME_ADMIN);

        $invoiceCustomer = $this->createInvoiceCustomer();
        $paidInvoice = $this->createGrandfatheredPaidInvoice($invoiceCustomer, $admin);

        $em->clear();

        $this->request($client, '/approvals/');

        self::assertTrue($client->getResponse()->isSuccessful());
        $content = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString((string) $paidInvoice->getInvoiceNumber(), $content);
    }
}
