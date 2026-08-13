<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\Customer;
use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Entity\ExpenseApprovalLevel;
use App\Entity\Project;
use App\Entity\Quotation;
use App\Entity\QuotationLine;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

/**
 * Covers ExpenseController: routing/security, form orchestration (percentage
 * validation + AllocationSplitter recompute - controller-only logic not
 * covered by the Phase 2/3 unit and integration tests), and the end-to-end
 * submit/approve/reject/delete/charge workflow.
 *
 * CSRF tokens are always read back from the actually rendered page (never
 * pre-generated via a detached session, see AbstractControllerBaseTestCase::
 * getCsrfToken()'s doc) - a token minted outside the real request/session
 * does not validate against the CSRF token stored in that real session.
 */
#[Group('integration')]
class ExpenseControllerTest extends AbstractControllerBaseTestCase
{
    /** @return array{0: Customer, 1: Project} */
    private function createCustomerAndProject(string $suffix = ''): array
    {
        $customer = new Customer('Expense test customer ' . uniqid() . $suffix);
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $this->getEntityManager()->persist($customer);

        $project = new Project();
        $project->setName('Expense test project ' . uniqid() . $suffix);
        $project->setCustomer($customer);
        $this->getEntityManager()->persist($project);
        $this->getEntityManager()->flush();

        return [$customer, $project];
    }

    private function createDraftExpenseWithAllocation(Project $project, int $amountClp, string $percentage = '100.00', ?User $createdBy = null): Expense
    {
        $expense = new Expense();
        $expense->setDescription('Draft expense ' . uniqid());
        $expense->setAmount($amountClp);
        $expense->setExpenseDate(new \DateTimeImmutable('2026-08-01'));
        if (null !== $createdBy) {
            $expense->setCreatedBy($createdBy);
        }
        $allocation = (new ExpenseAllocation())->setProject($project)->setPercentage($percentage);
        $expense->addAllocation($allocation);
        $this->getEntityManager()->persist($expense);
        $this->getEntityManager()->flush();

        return $expense;
    }

    /**
     * Extracts a CSRF token from an actually rendered page - a token minted
     * in a detached test session never validates against the real one.
     */
    private function extractToken(HttpKernelBrowser $client, string $url, string $inputSelector): string
    {
        $crawler = $this->request($client, $url);
        $value = $crawler->filter($inputSelector)->attr('value');
        self::assertIsString($value, 'Could not find token via selector "' . $inputSelector . '" on ' . $url);

        return $value;
    }

    public function testExpenseRoutesAreSecured(): void
    {
        $this->assertUrlIsSecured('/expense/');
        $this->assertUrlIsSecured('/expense/pending');
    }

    public function testRegularUserCannotAccessExpenseManagement(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/expense/');
    }

    public function testTeamleadCanCreateDraftExpenseWithAllocationAndAmountIsSplit(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        [, $project] = $this->createCustomerAndProject();

        $token = $this->extractToken($client, '/expense/create', 'input[name="expense_form[_token]"]');
        $this->request($client, '/expense/create', 'POST', [
            'expense_form' => [
                'description' => 'Client dinner',
                'amount' => '100000',
                'expenseDate' => $this->formatDate(new \DateTime('2026-08-01')),
                'allocations' => [
                    0 => ['project' => $project->getId(), 'percentage' => '100.00'],
                ],
                '_token' => $token,
            ],
        ]);

        $this->assertIsRedirect($client);

        $em = $this->getEntityManager();
        $expense = $em->getRepository(Expense::class)->findOneBy(['description' => 'Client dinner']);
        self::assertInstanceOf(Expense::class, $expense);
        self::assertSame(Expense::STATUS_DRAFT, $expense->getStatus());
        self::assertCount(1, $expense->getAllocations());
        $allocation = $expense->getAllocations()->first();
        self::assertInstanceOf(ExpenseAllocation::class, $allocation);
        self::assertSame(100000, $allocation->getAmountClp());
    }

    public function testCreateIsRejectedWhenAllocationsExceed100Percent(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        [, $projectA] = $this->createCustomerAndProject('a');
        [, $projectB] = $this->createCustomerAndProject('b');

        $token = $this->extractToken($client, '/expense/create', 'input[name="expense_form[_token]"]');
        $this->request($client, '/expense/create', 'POST', [
            'expense_form' => [
                'description' => 'Overspent expense',
                'amount' => '100000',
                'expenseDate' => $this->formatDate(new \DateTime('2026-08-01')),
                'allocations' => [
                    0 => ['project' => $projectA->getId(), 'percentage' => '60.00'],
                    1 => ['project' => $projectB->getId(), 'percentage' => '50.00'],
                ],
                '_token' => $token,
            ],
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertFalse($client->getResponse()->isRedirect());

        $em = $this->getEntityManager();
        self::assertNull($em->getRepository(Expense::class)->findOneBy(['description' => 'Overspent expense']));
    }

    public function testEditRecalculatesAllocationAmounts(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        [, $project] = $this->createCustomerAndProject();
        $expense = $this->createDraftExpenseWithAllocation($project, 100000);
        $expenseId = $expense->getId();
        $description = $expense->getDescription();
        self::assertIsString($description);

        $token = $this->extractToken($client, '/expense/' . $expenseId . '/edit', 'input[name="expense_form[_token]"]');
        $this->request($client, '/expense/' . $expenseId . '/edit', 'POST', [
            'expense_form' => [
                'description' => $description,
                'amount' => '200000',
                'expenseDate' => $this->formatDate(new \DateTime('2026-08-01')),
                'allocations' => [
                    0 => ['project' => $project->getId(), 'percentage' => '100.00'],
                ],
                '_token' => $token,
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/expense/' . $expenseId));

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Expense::class)->find($expenseId);
        self::assertInstanceOf(Expense::class, $reloaded);
        self::assertSame(200000, $reloaded->getAmount());
        $reloadedAllocation = $reloaded->getAllocations()->first();
        self::assertInstanceOf(ExpenseAllocation::class, $reloadedAllocation);
        self::assertSame(200000, $reloadedAllocation->getAmountClp());
    }

    public function testSubmitIsRejectedWhenAllocationsDoNotSumToExactly100Percent(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        [, $project] = $this->createCustomerAndProject();
        $expense = $this->createDraftExpenseWithAllocation($project, 100000, '60.00');
        $expenseId = $expense->getId();

        $token = $this->extractToken($client, '/expense/' . $expenseId, 'form[action$="/submit"] input[name=_token]');
        $this->request($client, '/expense/' . $expenseId . '/submit', 'POST', ['_token' => $token]);

        $this->assertIsRedirect($client, $this->createUrl('/expense/' . $expenseId));
        $client->followRedirect();
        $this->assertHasFlashError($client);

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Expense::class)->find($expenseId);
        self::assertInstanceOf(Expense::class, $reloaded);
        self::assertSame(Expense::STATUS_DRAFT, $reloaded->getStatus());
    }

    public function testSubmitAndApproveMovesExpenseToApproved(): void
    {
        // The client must be created before any other call that boots the
        // kernel (e.g. loadUserFromDatabase/getEntityManager), otherwise
        // WebTestCase::createClient() throws "kernel should only be booted once".
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $creatorUser = $this->loadUserFromDatabase(UserFixtures::USERNAME_ADMIN);
        [, $project] = $this->createCustomerAndProject();
        $expense = $this->createDraftExpenseWithAllocation($project, 100000, '100.00', $creatorUser);
        $expenseId = $expense->getId();

        $submitToken = $this->extractToken($client, '/expense/' . $expenseId, 'form[action$="/submit"] input[name=_token]');
        $this->request($client, '/expense/' . $expenseId . '/submit', 'POST', ['_token' => $submitToken]);
        $this->assertIsRedirect($client, $this->createUrl('/expense/' . $expenseId));

        $approveToken = $this->extractToken($client, '/expense/' . $expenseId, 'form[action$="/approve"] input[name=_token]');
        $this->request($client, '/expense/' . $expenseId . '/approve', 'POST', [
            'expense_approval_decision_form' => ['note' => '', '_token' => $approveToken],
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/expense/' . $expenseId));

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(Expense::class)->find($expenseId);
        self::assertInstanceOf(Expense::class, $reloaded);
        self::assertSame(Expense::STATUS_APPROVED, $reloaded->getStatus());
        self::assertCount(1, $reloaded->getApprovals());
    }

    public function testCreatorCannotApproveOwnExpense(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $creatorUser = $this->loadUserFromDatabase(UserFixtures::USERNAME_TEAMLEAD);
        [, $project] = $this->createCustomerAndProject();
        $expense = $this->createDraftExpenseWithAllocation($project, 100000, '100.00', $creatorUser);
        $expenseId = $expense->getId();

        $submitToken = $this->extractToken($client, '/expense/' . $expenseId, 'form[action$="/submit"] input[name=_token]');
        $this->request($client, '/expense/' . $expenseId . '/submit', 'POST', ['_token' => $submitToken]);
        $this->assertIsRedirect($client);

        // The expense is now pending_approval, its own creator: the "decide"
        // form (approve/reject) is only rendered for a user who could act on
        // it - so the creator's own view of the page renders no such form.
        // The route itself must still deny a forged submission.
        $this->request($client, '/expense/' . $expenseId . '/approve', 'POST', [
            'expense_approval_decision_form' => ['note' => '', '_token' => 'irrelevant-because-denied-before-csrf'],
        ]);

        $this->assertAccessDenied($client);
    }

    public function testRejectDiscardsAccumulatedApprovals(): void
    {
        $teamlead = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $em = $this->getEntityManager();
        $level2 = (new ExpenseApprovalLevel())->setLevel(2)->setMinAmount(50000)->setRequiredRole(User::ROLE_ADMIN);
        $em->persist($level2);
        $em->flush();

        $creatorUser = $this->loadUserFromDatabase(UserFixtures::USERNAME_SUPER_ADMIN);
        [, $project] = $this->createCustomerAndProject();
        $expense = $this->createDraftExpenseWithAllocation($project, 100000, '100.00', $creatorUser);
        $expenseId = $expense->getId();

        $submitToken = $this->extractToken($teamlead, '/expense/' . $expenseId, 'form[action$="/submit"] input[name=_token]');
        $this->request($teamlead, '/expense/' . $expenseId . '/submit', 'POST', ['_token' => $submitToken]);
        $this->assertIsRedirect($teamlead);

        $approveToken = $this->extractToken($teamlead, '/expense/' . $expenseId, 'form[action$="/approve"] input[name=_token]');
        $this->request($teamlead, '/expense/' . $expenseId . '/approve', 'POST', [
            'expense_approval_decision_form' => ['note' => '', '_token' => $approveToken],
        ]);
        $this->assertIsRedirect($teamlead, $this->createUrl('/expense/' . $expenseId));

        // Switch identity on the SAME client instance (a second createClient()
        // call within one test is not supported by WebTestCase).
        $adminUser = $this->loadUserFromDatabase(UserFixtures::USERNAME_ADMIN);
        $teamlead->loginUser($adminUser, 'secured_area');
        $rejectToken = $this->extractToken($teamlead, '/expense/' . $expenseId, 'form[action$="/reject"] input[name=_token]');
        $this->request($teamlead, '/expense/' . $expenseId . '/reject', 'POST', [
            'expense_approval_decision_form' => ['note' => 'Missing receipt', '_token' => $rejectToken],
        ]);
        $this->assertIsRedirect($teamlead, $this->createUrl('/expense/' . $expenseId));

        $em->clear();
        $reloaded = $em->getRepository(Expense::class)->find($expenseId);
        self::assertInstanceOf(Expense::class, $reloaded);
        self::assertSame(Expense::STATUS_REJECTED, $reloaded->getStatus());
        self::assertSame(0, $reloaded->getCurrentLevel());
    }

    public function testDeleteRemovesDraftExpense(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        [, $project] = $this->createCustomerAndProject();
        $expense = $this->createDraftExpenseWithAllocation($project, 100000);
        $expenseId = $expense->getId();

        $token = $this->extractToken($client, '/expense/' . $expenseId, 'form[action$="/delete"] input[name=_token]');
        $this->request($client, '/expense/' . $expenseId . '/delete', 'POST', ['_token' => $token]);

        $this->assertIsRedirect($client, $this->createUrl('/expense/'));
        $client->followRedirect();
        $this->assertHasFlashDeleteSuccess($client);

        $em = $this->getEntityManager();
        self::assertNull($em->getRepository(Expense::class)->find($expenseId));
    }

    public function testDeleteIsBlockedForApprovedExpense(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        [, $project] = $this->createCustomerAndProject();
        $expense = $this->createDraftExpenseWithAllocation($project, 100000);
        $expense->submitForApproval(1);
        $expense->clearLevel(1);
        $this->getEntityManager()->flush();
        $expenseId = $expense->getId();

        // Denied by the voter (not draft/rejected) before the CSRF check is
        // ever reached - the view page renders no delete form for it either.
        $this->request($client, '/expense/' . $expenseId . '/delete', 'POST', ['_token' => 'irrelevant']);

        $this->assertAccessDenied($client);
    }

    public function testChargeAddsQuotationLineToTargetQuotation(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();
        [$customer, $project] = $this->createCustomerAndProject();
        // This quotation already has a line and a validUntil date to cover
        // the "existing draft quotation" cross-charge target scenario; see
        // testChargeSucceedsForFreshlyCreatedQuotationWithNoLinesOrValidUntil
        // below for the freshly-created (no line/no validUntil) scenario.
        $quotation = (new Quotation())->setCustomer($customer)->setProject($project)->setCurrency(Quotation::CURRENCY_CLP);
        $quotation->setValidUntil(new \DateTimeImmutable('2026-08-20'));
        $quotation->addLine((new QuotationLine())->setDescription('Existing item')->setQuantity('1')->setUnitPrice('1'));
        $em->persist($quotation);

        $expense = $this->createDraftExpenseWithAllocation($project, 100000);
        $expense->submitForApproval(1);
        $expense->clearLevel(1);
        $allocation = $expense->getAllocations()->first();
        self::assertInstanceOf(ExpenseAllocation::class, $allocation);
        $allocation->setAmountClp(100000);
        $em->flush();
        $allocationId = $allocation->getId();

        $token = $this->extractToken(
            $client,
            '/expense/' . $expense->getId(),
            'form[action$="/allocation/' . $allocationId . '/charge"] input[name="expense_charge_form[_token]"]'
        );
        $this->request($client, '/expense/allocation/' . $allocationId . '/charge', 'POST', [
            'expense_charge_form' => [
                'quotation' => $quotation->getId(),
                '_token' => $token,
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/expense/' . $expense->getId()));

        $em->clear();
        $reloadedAllocation = $em->getRepository(ExpenseAllocation::class)->find($allocationId);
        self::assertInstanceOf(ExpenseAllocation::class, $reloadedAllocation);
        self::assertTrue($reloadedAllocation->isCharged());
        $reloadedQuotation = $em->getRepository(Quotation::class)->find($quotation->getId());
        self::assertInstanceOf(Quotation::class, $reloadedQuotation);
        self::assertCount(2, $reloadedQuotation->getLines());
    }

    /**
     * Regression test for the `ExpenseChargeForm` cascade-validation bug
     * (fixed in Phase 6, see `ExpenseChargeForm::configureOptions`): a
     * freshly-created draft quotation with no lines and no `validUntil` yet
     * MUST still be a valid cross-charge target. Before the fix, Symfony's
     * Form Validator cascaded onto `Quotation`'s own class-level constraints
     * (`Assert\Count(min: 1)` on lines, `Assert\NotNull` on `validUntil`)
     * because the 'quotation' field's model data is that entity object,
     * silently rejecting the charge with a generic error and no field-level
     * feedback.
     */
    public function testChargeSucceedsForFreshlyCreatedQuotationWithNoLinesOrValidUntil(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $em = $this->getEntityManager();
        [$customer, $project] = $this->createCustomerAndProject();
        $quotation = (new Quotation())->setCustomer($customer)->setProject($project)->setCurrency(Quotation::CURRENCY_CLP);
        $em->persist($quotation);

        $expense = $this->createDraftExpenseWithAllocation($project, 100000);
        $expense->submitForApproval(1);
        $expense->clearLevel(1);
        $allocation = $expense->getAllocations()->first();
        self::assertInstanceOf(ExpenseAllocation::class, $allocation);
        $allocation->setAmountClp(100000);
        $em->flush();
        $allocationId = $allocation->getId();

        $crawler = $this->request($client, '/expense/' . $expense->getId());
        $options = $crawler->filter('select[name="expense_charge_form[quotation]"] option')->each(static fn ($node) => $node->attr('value'));
        self::assertContains((string) $quotation->getId(), $options, 'The freshly-created quotation must be a selectable choice.');

        $token = $this->extractToken(
            $client,
            '/expense/' . $expense->getId(),
            'form[action$="/allocation/' . $allocationId . '/charge"] input[name="expense_charge_form[_token]"]'
        );
        $this->request($client, '/expense/allocation/' . $allocationId . '/charge', 'POST', [
            'expense_charge_form' => [
                'quotation' => $quotation->getId(),
                '_token' => $token,
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/expense/' . $expense->getId()));
        $client->followRedirect();
        $this->assertHasFlashSuccess($client);

        $em->clear();
        $reloadedAllocation = $em->getRepository(ExpenseAllocation::class)->find($allocationId);
        self::assertInstanceOf(ExpenseAllocation::class, $reloadedAllocation);
        self::assertTrue($reloadedAllocation->isCharged());
        $reloadedQuotation = $em->getRepository(Quotation::class)->find($quotation->getId());
        self::assertInstanceOf(Quotation::class, $reloadedQuotation);
        self::assertCount(1, $reloadedQuotation->getLines());
    }

    public function testPendingListShowsExpensesAwaitingApprovalExcludingOwnExpenses(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $em = $this->getEntityManager();
        [, $project] = $this->createCustomerAndProject();

        $adminUser = $this->loadUserFromDatabase(UserFixtures::USERNAME_ADMIN);
        $teamleadUser = $this->loadUserFromDatabase(UserFixtures::USERNAME_TEAMLEAD);

        $othersExpense = $this->createDraftExpenseWithAllocation($project, 100000, '100.00', $adminUser);
        $othersExpense->submitForApproval(1);
        $em->flush();

        $ownExpense = $this->createDraftExpenseWithAllocation($project, 50000, '100.00', $teamleadUser);
        $ownExpense->submitForApproval(1);
        $em->flush();

        $this->request($client, '/expense/pending');

        self::assertTrue($client->getResponse()->isSuccessful());
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString((string) $othersExpense->getDescription(), $content);
        self::assertStringNotContainsString((string) $ownExpense->getDescription(), $content);
    }
}
