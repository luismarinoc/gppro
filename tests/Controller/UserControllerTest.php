<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Tests\DataFixtures\TimesheetFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

#[Group('integration')]
class UserControllerTest extends AbstractControllerBaseTestCase
{
    public function testIsSecure(): void
    {
        $this->assertUrlIsSecured('/admin/user/');
    }

    public function testIsSecureForRole(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/admin/user/');
    }

    public function testIndexAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/user/');
        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_user_admin', 7);
        $this->assertPageActions($client, [
            'download toolbar-action' => $this->createUrl('/admin/user/export'),
            'create modal-ajax-form' => $this->createUrl('/admin/user/create'),
            'dropdown-item action-weekly' => $this->createUrl('/reporting/users/week'),
            'dropdown-item action-monthly' => $this->createUrl('/reporting/users/month'),
            'dropdown-item action-yearly' => $this->createUrl('/reporting/users/year'),
        ]);
    }

    public function testIndexActionWithSearchTermQuery(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $this->request($client, '/admin/user/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form.searchform')->form();
        $client->submit($form, [
            'searchTerm' => 'hourly_rate:35 tony',
            'role' => 'ROLE_TEAMLEAD',
            'visibility' => 1,
            'size' => 50,
            'page' => 1,
        ]);

        self::assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_user_admin', 1);
    }

    public function testExportIsSecureForRole(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/admin/user/export');
    }

    public function testExportAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/user/export');
        $this->assertExcelExportResponse($client, 'gppro-users_');
    }

    public function testExportActionWithSearchTermQuery(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $this->request($client, '/admin/user/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form.searchform')->form();
        $form->getFormNode()->setAttribute('action', $this->createUrl('/admin/user/export'));
        $client->submit($form, [
            'searchTerm' => 'hourly_rate:35 tony',
            'role' => 'ROLE_TEAMLEAD',
            'visibility' => 1,
            'size' => 50,
            'page' => 1,
        ]);

        $this->assertExcelExportResponse($client, 'gppro-users_');
    }

    public function testCreateAction(): void
    {
        $username = '亚历山德拉' . uniqid();
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/user/create');
        $form = $client->getCrawler()->filter('form[name=user_create]')->form();
        $client->submit($form, [
            'user_create' => [
                'username' => $username,
                'alias' => $username,
                'plainPassword' => ['first' => 'Passw0rd', 'second' => 'Passw0rd'],
                'email' => 'foobar@example.com',
                'enabled' => 1,
            ]
        ]);

        $location = $this->assertIsModalRedirect($client, '/profile/' . urlencode($username) . '/edit');
        $this->requestPure($client, $location);

        $form = $client->getCrawler()->filter('form[name=user_edit]')->form();
        self::assertEquals($username, $form->get('user_edit[alias]')->getValue());
    }

    public function testDeleteAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $this->request($client, '/admin/user/4/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/user/4/delete'), $form->getUri());
        $client->submit($form);

        $client->followRedirect();
        $this->assertHasDataTable($client);
        $this->assertHasFlashSuccess($client);

        $this->request($client, '/admin/user/4/edit');
        self::assertFalse($client->getResponse()->isSuccessful());
    }

    public function testDeleteActionWithTimesheetEntries(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $em = $this->getEntityManager();
        $user = $this->getUserByRole(User::ROLE_USER);

        $fixture = new TimesheetFixtures();
        $fixture->setUser($user);
        $fixture->setAmount(10);
        $this->importFixture($fixture);

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(10, \count($timesheets));

        $this->request($client, '/admin/user/' . $user->getId() . '/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/user/' . $user->getId() . '/delete'), $form->getUri());
        $client->submit($form);

        $this->assertIsRedirect($client, $this->createUrl('/admin/user/'));
        $client->followRedirect();
        $this->assertHasFlashDeleteSuccess($client);

        $em->clear();
        $timesheets = $em->getRepository(Timesheet::class)->count([]);
        self::assertEquals(0, $timesheets);

        $this->request($client, '/admin/user/' . $user->getId() . '/edit');
        self::assertFalse($client->getResponse()->isSuccessful());
    }

    public function testDeleteActionWithUserReplacementAndTimesheetEntries(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $em = $this->getEntityManager();
        $user = $this->getUserByRole(User::ROLE_USER);
        $userNew = $this->getUserByRole(User::ROLE_TEAMLEAD);

        $this->assertNotEquals($userNew->getId(), $user->getId());

        $fixture = new TimesheetFixtures();
        $fixture->setUser($user);
        $fixture->setAmount(10);
        $this->importFixture($fixture);

        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(10, \count($timesheets));
        foreach ($timesheets as $timesheet) {
            self::assertEquals($user->getId(), $timesheet->getUser()->getId());
        }

        $this->request($client, '/admin/user/' . $user->getId() . '/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/user/' . $user->getId() . '/delete'), $form->getUri());
        $client->submit($form, [
            'form' => [
                'user' => $userNew->getId()
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/admin/user/'));
        $client->followRedirect();
        $this->assertHasFlashDeleteSuccess($client);

        $em->clear();
        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        self::assertEquals(10, \count($timesheets));
        foreach ($timesheets as $timesheet) {
            self::assertEquals($userNew->getId(), $timesheet->getUser()->getId());
        }

        $this->request($client, '/admin/user/' . $user->getId() . '/edit');
        self::assertFalse($client->getResponse()->isSuccessful());
    }

    /**
     * The quick-action links carry a real, session-bound CSRF token generated
     * by UserSubscriber::onActions() when the index page renders. A token
     * minted via a detached test session (AbstractControllerBaseTestCase::
     * getCsrfToken()) does not validate against that real session - see
     * ExpenseControllerTest's class docblock for the same caveat - so the
     * only reliable way to get a real, valid token is to read it back from
     * the actually rendered dropdown link.
     */
    private function extractQuickActionUrl(HttpKernelBrowser $client, int $userId, string $routeNamePart): string
    {
        // visibility=3 (SHOW_BOTH) so disabled/pending-approval users (which the default
        // SHOW_VISIBLE filter would hide) also render their row and quick-action links
        $crawler = $this->request($client, '/admin/user/?visibility=3');
        $link = $crawler->filter('a[href*="/admin/user/' . $userId . '/' . $routeNamePart . '/"]');
        self::assertGreaterThan(0, $link->count(), 'Could not find quick-action link for user ' . $userId . ' and route "' . $routeNamePart . '"');

        $href = $link->attr('href');
        self::assertIsString($href);

        return $href;
    }

    public function testForcePasswordResetActionSetsRequiresPasswordResetFlag(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $user = $this->getUserByRole(User::ROLE_USER);
        self::assertFalse($user->requiresPasswordReset());
        self::assertNotNull($user->getId());

        $url = $this->extractQuickActionUrl($client, $user->getId(), 'force-password-reset');
        $this->requestPure($client, $url, 'POST');

        $this->assertIsRedirect($client, $this->createUrl('/admin/user/'));

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->requiresPasswordReset());
    }

    public function testForcePasswordResetActionIsDeniedForNonSuperAdmin(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $user = $this->getUserByRole(User::ROLE_USER);

        // ROLE_ADMIN cannot even see the quick-action link (isGranted('password', $user)
        // is false for another user - only ROLE_SUPER_ADMIN has PROFILE_OTHER), so the
        // denial is exercised directly against the route with a syntactically valid but
        // non-matching token segment.
        $this->request($client, '/admin/user/' . $user->getId() . '/force-password-reset/irrelevant-because-denied-before-csrf', 'POST');

        self::assertEquals(403, $client->getResponse()->getStatusCode());

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->requiresPasswordReset());
    }

    public function testForcePasswordResetActionRejectsInvalidCsrfToken(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $user = $this->getUserByRole(User::ROLE_USER);

        $this->request($client, '/admin/user/' . $user->getId() . '/force-password-reset/not-a-real-token', 'POST');

        $this->assertIsRedirect($client, $this->createUrl('/admin/user/'));
        $client->followRedirect();
        $this->assertHasFlashError($client);

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->requiresPasswordReset());
    }

    public function testRevokeRememberMeActionChangesSecuritySignatureWithoutTouchingSession(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $user = $this->getUserByRole(User::ROLE_USER);
        $previousSignature = $user->getSignatureDate();
        self::assertNotNull($user->getId());

        $url = $this->extractQuickActionUrl($client, $user->getId(), 'revoke-remember-me');
        $this->requestPure($client, $url, 'POST');

        $this->assertIsRedirect($client, $this->createUrl('/admin/user/'));

        // no session store service is involved: the action only rotates the
        // signature date, it never touches session storage/handlers
        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertNotEquals($previousSignature, $reloaded->getSignatureDate());
    }

    public function testRevokeRememberMeActionIsDeniedForNonSuperAdmin(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $user = $this->getUserByRole(User::ROLE_USER);
        $previousSignature = $user->getSignatureDate();

        $this->request($client, '/admin/user/' . $user->getId() . '/revoke-remember-me/irrelevant-because-denied-before-csrf', 'POST');

        self::assertEquals(403, $client->getResponse()->getStatusCode());

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertEquals($previousSignature, $reloaded->getSignatureDate());
    }

    /**
     * Builds a pending-approval user directly (not via the HTTP self-registration flow), so the row
     * already exists in the database before the admin quick-action requests are made against it.
     */
    private function createPendingUser(string $username, string $email): User
    {
        $em = $this->getEntityManager();

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setLanguage('en');
        $user->setEnabled(false);
        $user->setPassword('irrelevant-hash-not-used-in-this-test');
        $user->setEmailConfirmedAt(new \DateTimeImmutable('-1 hour'));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testApproveActionEnablesUserAndSendsApprovalEmail(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $user = $this->createPendingUser('pendingapprove', 'pendingapprove@example.com');
        self::assertNotNull($user->getId());
        self::assertTrue($user->isPendingApproval());

        $url = $this->extractQuickActionUrl($client, $user->getId(), 'approve');
        $this->requestPure($client, $url, 'POST');

        $this->assertIsRedirect($client, $this->createUrl('/admin/user/'));

        self::assertEmailCount(1);

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isEnabled());
        self::assertFalse($reloaded->isPendingApproval());
    }

    public function testApproveActionIsDeniedForNonSuperAdmin(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $user = $this->createPendingUser('pendingapprovedenied', 'pendingapprovedenied@example.com');

        $this->request($client, '/admin/user/' . $user->getId() . '/approve/irrelevant-because-denied-before-csrf', 'POST');

        self::assertEquals(403, $client->getResponse()->getStatusCode());
        self::assertEmailCount(0);

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isEnabled());
    }

    public function testRejectActionSetsRejectedAtWithoutEnablingOrEmailingAndKeepsTheRow(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $user = $this->createPendingUser('pendingreject', 'pendingreject@example.com');
        self::assertNotNull($user->getId());
        self::assertTrue($user->isPendingApproval());

        $url = $this->extractQuickActionUrl($client, $user->getId(), 'reject');
        $this->requestPure($client, $url, 'POST');

        $this->assertIsRedirect($client, $this->createUrl('/admin/user/'));

        self::assertEmailCount(0);

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isEnabled());
        self::assertNotNull($reloaded->getRejectedAt());
        self::assertFalse($reloaded->isPendingApproval());
    }

    public function testIndexActionShowsPendingApprovalBadgeOnlyForPendingUsers(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->createPendingUser('pendingbadge', 'pendingbadge@example.com');

        // visibility=3 (SHOW_BOTH) so the disabled pending user's row also renders
        $this->request($client, '/admin/user/?visibility=3');
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        // exactly one pending user was created against the default (all-enabled) fixtures,
        // so the badge must appear exactly once - proving it is not shown for every row
        self::assertSame(1, substr_count($content, 'Pending approval'));
    }

    public function testRejectActionIsDeniedForNonSuperAdmin(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $user = $this->createPendingUser('pendingrejectdenied', 'pendingrejectdenied@example.com');

        $this->request($client, '/admin/user/' . $user->getId() . '/reject/irrelevant-because-denied-before-csrf', 'POST');

        self::assertEquals(403, $client->getResponse()->getStatusCode());
        self::assertEmailCount(0);

        $em = $this->getEntityManager();
        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isEnabled());
        self::assertNull($reloaded->getRejectedAt());
    }

    #[DataProvider('getValidationTestData')]
    public function testValidationForCreateAction(array $formData, array $validationFields): void
    {
        $this->assertFormHasValidationError(
            User::ROLE_SUPER_ADMIN,
            '/admin/user/create',
            'form[name=user_create]',
            $formData,
            $validationFields
        );
    }

    public static function getValidationTestData()
    {
        return [
            [
                // invalid fields: username, password_second, email, enabled
                [
                    'user_create' => [
                        'username' => '', // empty
                        'plainPassword' => ['first' => 'sdfsdf123'], // missing second
                        'alias' => 'ycvyxcb',
                        'title' => '34rtwrtewrt',
                        'email' => '', // empty email
                    ]
                ],
                [
                    '#user_create_username',
                    '#user_create_plainPassword_first',
                    '#user_create_email',
                ]
            ],
            // invalid fields: username, password, email
            [
                [
                    'user_create' => [
                        'username' => 'x', // too short
                        'plainPassword' => ['first' => 'sdfsdf123', 'second' => 'sdfxxxxxxx'], // do not match
                        'alias' => 'Boo',
                        'title' => 'Foo',
                        'email' => 'ydfbvsdfgs', // invalid email
                    ]
                ],
                [
                    '#user_create_username',
                    '#user_create_plainPassword_first',
                    '#user_create_email',
                ]
            ],
            // invalid fields: password (too short)
            [
                [
                    'user_create' => [
                        'username' => 'test123',
                        'plainPassword' => ['first' => 'test123', 'second' => 'test123'],
                        'alias' => 'ycvyxcb',
                        'title' => '34rtwrtewrt',
                        'email' => 'ydfbvsdfgs@example.com',
                    ]
                ],
                [
                    '#user_create_plainPassword_first',
                ]
            ],
            // invalid fields: alias (special chars), title (special chars, too long)
            [
                [
                    'user_create' => [
                        'username' => 'test1231',
                        'plainPassword' => ['first' => 'A-Real-Password.1', 'second' => 'A-Real-Password.1'],
                        'alias' => '""ycvyx<cb""',
                        'title' => 'sdfgsdfgsd fgsdf "<34rtwrtewrt>" gsdfg sdfg sdfg sdfg sdfg sdfg sdfg sdfgsdfgsd fgsdf gsdfgsgsdfgsdfgsdfgsdfg sdfg sdfg sdfg sdfg sdfg sdfgsdfgsd fgsdf gsdfgsgsdfgsdfgsdfg', // special chars + too long
                        'email' => 'ydfbvsdfgs@example.com',
                    ]
                ],
                [
                    '#user_create_alias',
                    '#user_create_title',
                ]
            ],
        ];
    }
}
