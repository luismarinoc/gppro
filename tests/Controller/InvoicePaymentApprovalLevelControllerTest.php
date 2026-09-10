<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\InvoicePaymentApprovalLevel;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * CSRF tokens are always read back from the actually rendered page - see
 * ExpenseControllerTest's class docblock for why a detached-session token
 * (AbstractControllerBaseTestCase::getCsrfToken()) does not validate here.
 */
#[Group('integration')]
class InvoicePaymentApprovalLevelControllerTest extends AbstractControllerBaseTestCase
{
    private function extractToken(HttpKernelBrowser $client, string $url, string $inputSelector): string
    {
        $crawler = $this->request($client, $url);
        $value = $crawler->filter($inputSelector)->attr('value');
        self::assertIsString($value, 'Could not find token via selector "' . $inputSelector . '" on ' . $url);

        return $value;
    }

    public function testApprovalLevelRoutesAreSecured(): void
    {
        $this->assertUrlIsSecured('/admin/invoice/payment-approval-levels/');
    }

    public function testAdminCannotAccessApprovalLevelManagement(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/admin/invoice/payment-approval-levels/');
    }

    public function testApprovalLevelListWorkflowPresentationPreservesPopulatedControls(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $em = $this->getEntityManager();
        $level1 = $em->getRepository(InvoicePaymentApprovalLevel::class)->findOneBy(['level' => 1]);
        self::assertInstanceOf(InvoicePaymentApprovalLevel::class, $level1);

        $approver = $this->loadUserFromDatabase(UserFixtures::USERNAME_TEAMLEAD);
        $level2 = (new InvoicePaymentApprovalLevel())
            ->setLevel(2)
            ->setMinAmount(500000)
            ->setRequiredRole(User::ROLE_ADMIN)
            ->setApproverUser($approver);
        $em->persist($level2);
        $em->flush();
        $level2Id = $level2->getId();
        self::assertIsInt($level2Id);

        $crawler = $this->request($client, '/admin/invoice/payment-approval-levels/');

        self::assertCount(1, $crawler->filter('.gp-workflow.gp-workflow--invoice'));
        self::assertCount(1, $crawler->filter('.gp-workflow--invoice__approval-levels.gp-workflow__surface'));
        self::assertCount(1, $crawler->filter('.gp-workflow__header'));
        self::assertCount(1, $crawler->filter('.gp-workflow__actions a[href$="/create"]'));
        self::assertCount(1, $crawler->filter('.gp-workflow__table-scroll > table.gp-workflow__table'));

        $level2Row = $crawler->filter('tr.alternative-link[data-href$="/' . $level2Id . '/edit"]');
        self::assertCount(1, $level2Row);
        self::assertStringContainsString('500000', $level2Row->text());
        self::assertStringContainsString($approver->getDisplayName(), $level2Row->text());
        self::assertCount(1, $level2Row->filter('a[href$="/' . $level2Id . '/edit"]'));
        $deleteForm = $level2Row->filter('form[method="post"][action$="/' . $level2Id . '/delete"]');
        self::assertCount(1, $deleteForm);
        self::assertNotSame('', $deleteForm->filter('input[name="_token"]')->attr('value'));

        $level1Row = $crawler->filter('tr.alternative-link[data-href$="/' . $level1->getId() . '/edit"]');
        self::assertCount(1, $level1Row);
        self::assertCount(0, $level1Row->filter('form[action$="/delete"]'));
    }

    public function testApprovalLevelListWorkflowPresentationPreservesEmptyState(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $em = $this->getEntityManager();
        foreach ($em->getRepository(InvoicePaymentApprovalLevel::class)->findAll() as $level) {
            $em->remove($level);
        }
        $em->flush();

        $crawler = $this->request($client, '/admin/invoice/payment-approval-levels/');
        $translator = self::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        self::assertCount(1, $crawler->filter('.gp-workflow.gp-workflow--invoice'));
        $emptyState = $crawler->filter('.gp-workflow-state.gp-workflow-state--empty');
        self::assertCount(1, $emptyState);
        self::assertSame(
            $translator->trans('invoice_payment_approval_level.none_found'),
            trim($emptyState->text())
        );
    }

    public function testApprovalLevelFormWorkflowPresentationPreservesSymfonyControls(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $em = $this->getEntityManager();
        $level = $em->getRepository(InvoicePaymentApprovalLevel::class)->findOneBy(['level' => 1]);
        self::assertInstanceOf(InvoicePaymentApprovalLevel::class, $level);

        $createCrawler = $this->request($client, '/admin/invoice/payment-approval-levels/create');
        self::assertCount(1, $createCrawler->filter('.gp-workflow.gp-workflow--invoice'));
        self::assertCount(1, $createCrawler->filter('form.gp-workflow__form[action$="/create"][method="post"]'));

        $crawler = $this->request($client, '/admin/invoice/payment-approval-levels/' . $level->getId() . '/edit');

        self::assertCount(1, $crawler->filter('.gp-workflow.gp-workflow--invoice'));
        self::assertCount(1, $crawler->filter('.gp-workflow--invoice__approval-level-form.gp-workflow__surface'));
        self::assertCount(1, $crawler->filter('.gp-workflow__header'));
        $form = $crawler->filter('form.gp-workflow__form[action$="/' . $level->getId() . '/edit"][method="post"]');
        self::assertCount(1, $form);
        self::assertCount(1, $form->filter('input[name="invoice_payment_approval_level_form[level]"]'));
        self::assertCount(1, $form->filter('input[name="invoice_payment_approval_level_form[minAmount]"]'));
        self::assertCount(1, $form->filter('[name="invoice_payment_approval_level_form[requiredRole]"]'));
        self::assertCount(1, $form->filter('[name="invoice_payment_approval_level_form[approverUser]"]'));
        self::assertCount(1, $form->filter('input[name="invoice_payment_approval_level_form[_token]"]'));
        self::assertCount(1, $form->filter('button[type="submit"]'));
        self::assertCount(1, $form->filter('a[href$="/admin/invoice/payment-approval-levels/"]'));
    }

    public function testSuperAdminCanListAndCreateApprovalLevel(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->request($client, '/admin/invoice/payment-approval-levels/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $token = $this->extractToken(
            $client,
            '/admin/invoice/payment-approval-levels/create',
            'input[name="invoice_payment_approval_level_form[_token]"]'
        );
        $this->request($client, '/admin/invoice/payment-approval-levels/create', 'POST', [
            'invoice_payment_approval_level_form' => [
                'level' => '2',
                'minAmount' => '500000.5',
                'requiredRole' => User::ROLE_ADMIN,
                '_token' => $token,
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/invoice/payment-approval-levels/'));

        $em = $this->getEntityManager();
        $level = $em->getRepository(InvoicePaymentApprovalLevel::class)->findOneBy(['level' => 2]);
        self::assertInstanceOf(InvoicePaymentApprovalLevel::class, $level);
        self::assertSame(500000.5, $level->getMinAmount());
    }

    public function testSuperAdminCanCreateApprovalLevelWithANamedApprover(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $approverUser = $this->loadUserFromDatabase(UserFixtures::USERNAME_TEAMLEAD);

        $token = $this->extractToken(
            $client,
            '/admin/invoice/payment-approval-levels/create',
            'input[name="invoice_payment_approval_level_form[_token]"]'
        );
        $this->request($client, '/admin/invoice/payment-approval-levels/create', 'POST', [
            'invoice_payment_approval_level_form' => [
                'level' => '2',
                'minAmount' => '500000',
                'requiredRole' => User::ROLE_ADMIN,
                'approverUser' => (string) $approverUser->getId(),
                '_token' => $token,
            ],
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/invoice/payment-approval-levels/'));

        $em = $this->getEntityManager();
        $level = $em->getRepository(InvoicePaymentApprovalLevel::class)->findOneBy(['level' => 2]);
        self::assertInstanceOf(InvoicePaymentApprovalLevel::class, $level);
        self::assertInstanceOf(User::class, $level->getApproverUser());
        self::assertSame($approverUser->getId(), $level->getApproverUser()->getId());
    }

    public function testNonMonotonicThresholdIsRejected(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $em = $this->getEntityManager();
        $em->persist((new InvoicePaymentApprovalLevel())->setLevel(2)->setMinAmount(1000000)->setRequiredRole(User::ROLE_ADMIN));
        $em->flush();

        $token = $this->extractToken(
            $client,
            '/admin/invoice/payment-approval-levels/create',
            'input[name="invoice_payment_approval_level_form[_token]"]'
        );
        $crawler = $this->request($client, '/admin/invoice/payment-approval-levels/create', 'POST', [
            'invoice_payment_approval_level_form' => [
                'level' => '3',
                'minAmount' => '500000',
                'requiredRole' => User::ROLE_SUPER_ADMIN,
                '_token' => $token,
            ],
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertFalse($client->getResponse()->isRedirect());
        self::assertCount(1, $crawler->filter('.gp-workflow.gp-workflow--invoice form.gp-workflow__form'));

        self::assertNull($em->getRepository(InvoicePaymentApprovalLevel::class)->findOneBy(['level' => 3]));
    }

    public function testInvalidApprovalLevelRetainsSymfonyErrorsInTheWorkflowForm(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $token = $this->extractToken(
            $client,
            '/admin/invoice/payment-approval-levels/create',
            'input[name="invoice_payment_approval_level_form[_token]"]'
        );

        $crawler = $this->request($client, '/admin/invoice/payment-approval-levels/create', 'POST', [
            'invoice_payment_approval_level_form' => [
                'level' => '',
                'minAmount' => '',
                'requiredRole' => '',
                '_token' => $token,
            ],
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());
        $form = $crawler->filter('form.gp-workflow__form');
        self::assertCount(1, $form);
        self::assertGreaterThan(0, $form->filter('.invalid-feedback')->count());
        self::assertCount(1, $form->filter('input[name="invoice_payment_approval_level_form[_token]"]'));
    }

    public function testLastRemainingLevelCannotBeDeleted(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $em = $this->getEntityManager();
        $level1 = $em->getRepository(InvoicePaymentApprovalLevel::class)->findOneBy(['level' => 1]);
        self::assertInstanceOf(InvoicePaymentApprovalLevel::class, $level1);
        $level1Id = $level1->getId();

        // Level 1 never renders a delete button (index.html.twig hides it),
        // but the CSRF token id for all delete forms ('invoice_payment_approval_level')
        // is shared - a level 2 row lets us read back a valid token to prove
        // the server-side guard rejects level 1 even if it were ever posted.
        $level2 = (new InvoicePaymentApprovalLevel())->setLevel(2)->setMinAmount(500000)->setRequiredRole(User::ROLE_ADMIN);
        $em->persist($level2);
        $em->flush();
        $level2Id = $level2->getId();

        $token = $this->extractToken(
            $client,
            '/admin/invoice/payment-approval-levels/',
            'form[action$="/' . $level2Id . '/delete"] input[name=_token]'
        );
        $this->request($client, '/admin/invoice/payment-approval-levels/' . $level1Id . '/delete', 'POST', ['_token' => $token]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/invoice/payment-approval-levels/'));
        $client->followRedirect();
        $this->assertHasFlashError($client);

        $em->clear();
        self::assertInstanceOf(InvoicePaymentApprovalLevel::class, $em->getRepository(InvoicePaymentApprovalLevel::class)->find($level1Id));
    }

    public function testSuperAdminCanDeleteANonBaseLevel(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $em = $this->getEntityManager();
        $level2 = (new InvoicePaymentApprovalLevel())->setLevel(2)->setMinAmount(500000)->setRequiredRole(User::ROLE_ADMIN);
        $em->persist($level2);
        $em->flush();
        $level2Id = $level2->getId();

        $token = $this->extractToken(
            $client,
            '/admin/invoice/payment-approval-levels/',
            'form[action$="/' . $level2Id . '/delete"] input[name=_token]'
        );
        $this->request($client, '/admin/invoice/payment-approval-levels/' . $level2Id . '/delete', 'POST', ['_token' => $token]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/invoice/payment-approval-levels/'));
        $client->followRedirect();
        $this->assertHasFlashDeleteSuccess($client);

        $em->clear();
        self::assertNull($em->getRepository(InvoicePaymentApprovalLevel::class)->find($level2Id));
    }
}
