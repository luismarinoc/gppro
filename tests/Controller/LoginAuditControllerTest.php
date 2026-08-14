<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\DataFixtures\UserFixtures;
use App\Entity\LoginAttempt;
use App\Entity\User;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * ROLE_SUPER_ADMIN-only login audit list (spec: "Audit list is restricted
 * to ROLE_SUPER_ADMIN", locked proposal decision #5).
 */
#[Group('integration')]
class LoginAuditControllerTest extends AbstractControllerBaseTestCase
{
    private function persistAttempt(User $user = null, string $attemptedUsername = 'someone', string $outcome = LoginAttempt::OUTCOME_SUCCESS, ?\DateTimeImmutable $createdAt = null): LoginAttempt
    {
        $attempt = new LoginAttempt();
        $attempt->setUser($user);
        $attempt->setAttemptedUsername($attemptedUsername);
        $attempt->setOutcome($outcome);
        $attempt->setIpAddress('203.0.113.9');
        $attempt->setUserAgent('TestAgent/1.0');
        $attempt->setCreatedAt($createdAt ?? new \DateTimeImmutable());
        if ($outcome === LoginAttempt::OUTCOME_FAILURE) {
            $attempt->setFailureReason('BadCredentialsException');
        }

        $em = $this->getEntityManager();
        $em->persist($attempt);
        $em->flush();

        return $attempt;
    }

    public function testRouteIsSecured(): void
    {
        $this->assertUrlIsSecured('/admin/login-audit/');
    }

    public function testAdminIsDeniedAccess(): void
    {
        // Explicit regression guard: ROLE_ADMIN alone must NOT grant access
        // (locked decision #5 — ROLE_SUPER_ADMIN only).
        $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/admin/login-audit/');
    }

    public function testSuperAdminCanViewAuditList(): void
    {
        // The kernel must be booted via getClientForAuthenticatedUser()
        // (createClient()) BEFORE getEntityManager()/getContainer() is
        // touched, or Symfony throws "kernel should only be booted once".
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->persistAttempt(null, 'someone', LoginAttempt::OUTCOME_SUCCESS);

        $this->request($client, '/admin/login-audit/');

        self::assertTrue($client->getResponse()->isSuccessful());
    }

    public function testSuperAdminCanFilterByOutcome(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->persistAttempt(null, 'success_user', LoginAttempt::OUTCOME_SUCCESS);
        $this->persistAttempt(null, 'failure_user', LoginAttempt::OUTCOME_FAILURE);

        $crawler = $this->request($client, '/admin/login-audit/?outcome=failure');

        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertStringContainsString('failure_user', $crawler->filter('body')->text());
        self::assertStringNotContainsString('success_user', $crawler->filter('body')->text());
    }

    public function testSuperAdminCanFilterByUser(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        /** @var UserRepository $userRepository */
        $userRepository = $this->getPrivateService(UserRepository::class);
        $superAdmin = $userRepository->findByUsername(UserFixtures::USERNAME_SUPER_ADMIN);
        self::assertInstanceOf(User::class, $superAdmin);

        $this->persistAttempt($superAdmin, UserFixtures::USERNAME_SUPER_ADMIN, LoginAttempt::OUTCOME_SUCCESS);
        $this->persistAttempt(null, 'unrelated_user', LoginAttempt::OUTCOME_SUCCESS);

        $crawler = $this->request($client, '/admin/login-audit/?user=' . $superAdmin->getId());

        self::assertTrue($client->getResponse()->isSuccessful());
        $text = $crawler->filter('body')->text();
        self::assertStringContainsString(UserFixtures::USERNAME_SUPER_ADMIN, $text);
        self::assertStringNotContainsString('unrelated_user', $text);
    }

    public function testOldRecordsRemainQueryableWithoutDateFilter(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->persistAttempt(null, 'ancient_user', LoginAttempt::OUTCOME_SUCCESS, new \DateTimeImmutable('2015-01-01 00:00:00'));

        $crawler = $this->request($client, '/admin/login-audit/');

        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertStringContainsString('ancient_user', $crawler->filter('body')->text());
    }
}
