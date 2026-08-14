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
        $this->request($client, '/admin/invoice/payment-approval-levels/create', 'POST', [
            'invoice_payment_approval_level_form' => [
                'level' => '3',
                'minAmount' => '500000',
                'requiredRole' => User::ROLE_SUPER_ADMIN,
                '_token' => $token,
            ],
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertFalse($client->getResponse()->isRedirect());

        self::assertNull($em->getRepository(InvoicePaymentApprovalLevel::class)->findOneBy(['level' => 3]));
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
