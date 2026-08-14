<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller\Security;

use App\Entity\User;
use App\Tests\Controller\AbstractControllerBaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Group('integration')]
class SelfRegistrationControllerTest extends AbstractControllerBaseTestCase
{
    private function assertRegisterActionWithDeactivatedFeature(string $route): void
    {
        $client = self::createClient();
        $this->setSystemConfiguration('user.registration', false);
        $this->request($client, $route);
        $this->assertRouteNotFound($client);
    }

    public function testRegisterWithDeactivatedFeature(): void
    {
        $this->assertRegisterActionWithDeactivatedFeature('/register/');
    }

    public function testCheckEmailWithDeactivatedFeature(): void
    {
        $this->assertRegisterActionWithDeactivatedFeature('/register/check-email');
    }

    public function testConfirmWithDeactivatedFeature(): void
    {
        $this->assertRegisterActionWithDeactivatedFeature('/register/confirm/123123');
    }

    public function testConfirmedWithDeactivatedFeature(): void
    {
        $this->assertRegisterActionWithDeactivatedFeature('/register/confirmed');
    }

    public function testRegisterAccountPageIsRendered(): void
    {
        $client = self::createClient();
        $this->setSystemConfiguration('user.registration', true);
        $this->request($client, '/register/');

        $response = $client->getResponse();
        self::assertTrue($response->isSuccessful());

        $content = $response->getContent();
        self::assertStringContainsString('<title>gppro</title>', $content);
        self::assertStringContainsString('Register a new account', $content);
        self::assertStringContainsString('<form name="user_registration_form" method="post" action="/en/register/"', $content);
        self::assertStringContainsString('<input type="email"', $content);
        self::assertStringContainsString('id="user_registration_form_email" name="user_registration_form[email]" required="required"', $content);
        self::assertStringContainsString('<input type="text"', $content);
        self::assertStringContainsString('id="user_registration_form_username" name="user_registration_form[username]" required="required" maxlength="64" pattern="', $content);
        self::assertStringContainsString('<input type="password"', $content);
        self::assertStringContainsString('id="user_registration_form_plainPassword_first" name="user_registration_form[plainPassword][first]" required="required"', $content);
        self::assertStringContainsString('id="user_registration_form_plainPassword_second" name="user_registration_form[plainPassword][second]" required="required"', $content);
        self::assertStringContainsString('<input type="hidden"', $content);
        self::assertStringContainsString('id="user_registration_form__token" name="user_registration_form[_token]"', $content);
        self::assertStringContainsString('>Register</button>', $content);
    }

    private function createUser(KernelBrowser $client, string $username, string $email, string $password): User
    {
        $this->setSystemConfiguration('user.registration', true);
        $this->request($client, '/register/');

        $response = $client->getResponse();
        self::assertTrue($response->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=user_registration_form]')->form();
        $client->submit($form, [
            'user_registration_form' => [
                'email' => $email,
                'username' => $username,
                'plainPassword' => [
                    'first' => $password,
                    'second' => $password,
                ],
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/register/check-email'));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());

        return $this->loadUserFromDatabase($username);
    }

    public function testCheckEmailWithoutEmail(): void
    {
        $client = self::createClient();
        $this->setSystemConfiguration('user.registration', true);
        $this->request($client, '/register/check-email');

        $this->assertIsRedirect($client, $this->createUrl('/register/'));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
    }

    public function testRegisterAccount(): void
    {
        $client = self::createClient();
        $this->createUser($client, 'example', 'register@example.com', 'test1234');

        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('<title>gppro</title>', $content);
        self::assertStringContainsString('An e-mail has been sent to register@example.com. It contains a link you must click to activate your account.', $content);
        self::assertStringContainsString('<a href="/en/login">', $content);
    }

    public function testConfirmWithInvalidToken(): void
    {
        $client = self::createClient();
        $this->setSystemConfiguration('user.registration', true);
        $this->request($client, '/register/confirm/1234567890');

        $this->assertIsRedirect($client, $this->createUrl('/login'));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
    }

    public function testConfirmAccount(): void
    {
        $client = self::createClient();
        $user = $this->createUser($client, 'example', 'register@example.com', 'test1234');

        $token = $user->getConfirmationToken();
        self::assertNotEmpty($token);
        self::assertFalse($user->isEnabled());
        self::assertNull($user->getEmailConfirmedAt());

        $this->request($client, '/register/confirm/' . $token);
        $this->assertIsRedirect($client, $this->createUrl('/register/pending-approval'));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('register@example.com', $content);

        $user = $this->loadUserFromDatabase('example');
        self::assertFalse($user->isEnabled());
        self::assertNotNull($user->getEmailConfirmedAt());
        self::assertNull($user->getConfirmationToken());
        self::assertTrue($user->isPendingApproval());

        // the user must NOT be auto-logged-in: any protected page still redirects to login
        $this->assertRequestIsSecured($client, '/homepage');
    }

    public function testConfirmAccountRendersPendingApprovalPageFromSessionForFreshClient(): void
    {
        $client = self::createClient();
        $user = $this->createUser($client, 'example', 'register@example.com', 'test1234');
        $token = $user->getConfirmationToken();

        $this->request($client, '/register/confirm/' . $token);
        $this->assertIsRedirect($client, $this->createUrl('/register/pending-approval'));

        $sessionCookie = $client->getCookieJar()->get('MOCKSESSID');
        self::assertNotNull($sessionCookie, 'No session cookie was set after confirming the email.');

        // simulate a brand-new, never-authenticated client that only carries the session cookie
        // (mirror-trap: catches a getUser()-based implementation, since this client never logged in)
        self::ensureKernelShutdown();
        $freshClient = self::createClient();
        $freshClient->getCookieJar()->set($sessionCookie);

        $this->request($freshClient, '/register/pending-approval');
        self::assertTrue($freshClient->getResponse()->isSuccessful());
        self::assertStringContainsString('register@example.com', $freshClient->getResponse()->getContent());
    }

    /**
     * Builds a previously-rejected user directly (not via the HTTP registration flow), so the row already
     * exists in the database before the test client makes its first request.
     */
    private function createRejectedUser(string $username, string $email, string $password): User
    {
        $em = $this->getEntityManager();

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setLanguage('en');
        $user->setEnabled(false);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setEmailConfirmedAt(new \DateTimeImmutable('-1 day'));
        $user->setRejectedAt(new \DateTimeImmutable('-1 hour'));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function submitRegistrationForm(KernelBrowser $client, string $username, string $email, string $password): void
    {
        $this->setSystemConfiguration('user.registration', true);
        $this->request($client, '/register/');

        $form = $client->getCrawler()->filter('form[name=user_registration_form]')->form();
        $client->submit($form, [
            'user_registration_form' => [
                'email' => $email,
                'username' => $username,
                'plainPassword' => [
                    'first' => $password,
                    'second' => $password,
                ],
            ]
        ]);
    }

    public function testRejectThenReregisterReusesSameRowAndClearsRejection(): void
    {
        $client = self::createClient();
        $rejected = $this->createRejectedUser('example', 'register@example.com', 'test1234');
        $originalId = $rejected->getId();
        self::assertNotNull($originalId);

        $this->submitRegistrationForm($client, 'example', 'register@example.com', 'newpassword1');

        $this->assertIsRedirect($client, $this->createUrl('/register/check-email'));

        $reloaded = $this->loadUserFromDatabase('example');
        self::assertSame($originalId, $reloaded->getId());
        self::assertNull($reloaded->getRejectedAt());
        self::assertNull($reloaded->getEmailConfirmedAt());
        self::assertNotEmpty($reloaded->getConfirmationToken());
        self::assertFalse($reloaded->isEnabled());
    }

    public function testRejectThenReregisterFullReviewCycleRepeats(): void
    {
        $client = self::createClient();
        $this->createRejectedUser('example', 'register@example.com', 'test1234');

        $this->submitRegistrationForm($client, 'example', 'register@example.com', 'newpassword1');
        $this->assertIsRedirect($client, $this->createUrl('/register/check-email'));

        $reloaded = $this->loadUserFromDatabase('example');
        $newToken = $reloaded->getConfirmationToken();
        self::assertNotEmpty($newToken);

        $this->request($client, '/register/confirm/' . $newToken);
        $this->assertIsRedirect($client, $this->createUrl('/register/pending-approval'));

        $confirmed = $this->loadUserFromDatabase('example');
        self::assertTrue($confirmed->isPendingApproval());
        self::assertFalse($confirmed->isEnabled());
    }

    public function testConfirmedAnonymousRedirectsToLogin(): void
    {
        $client = self::createClient();
        $this->setSystemConfiguration('user.registration', true);
        $this->request($client, '/register/confirmed');

        // AccessDeniedException redirects to login
        $this->assertIsRedirect($client, $this->createUrl('/login'));
        $client->followRedirect();
        self::assertTrue($client->getResponse()->isSuccessful());
    }

    #[DataProvider('getValidationTestData')]
    public function testRegisterActionWithValidationProblems(array $formData, array $validationFields): void
    {
        $client = self::createClient();
        $this->setSystemConfiguration('user.registration', true);

        $this->assertHasValidationError($client, '/register/', 'form[name=user_registration_form]', $formData, $validationFields);
    }

    public static function getValidationTestData(): array // @phpstan-ignore missingType.iterableValue
    {
        return [
            [
                // invalid fields: username, password_second, email
                [
                    'user_registration_form' => [
                        'username' => '',
                        'plainPassword' => ['first' => 'sdfsdf123'],
                        'email' => '',
                    ]
                ],
                [
                    '#user_registration_form_username',
                    '#user_registration_form_plainPassword_first',
                    '#user_registration_form_email',
                ]
            ],
            // invalid fields: username, password, email
            [
                [
                    'user_registration_form' => [
                        'username' => 'x',
                        'plainPassword' => ['first' => 'sdfsdf123', 'second' => 'sdfxxxxxxx'],
                        'email' => 'ydfbvsdfgs',
                    ]
                ],
                [
                    '#user_registration_form_username',
                    '#user_registration_form_plainPassword_first',
                    '#user_registration_form_email',
                ]
            ],
            // invalid fields: password (too short)
            [
                [
                    'user_registration_form' => [
                        'username' => 'test123',
                        'plainPassword' => ['first' => 'test123', 'second' => 'test123'],
                        'email' => 'ydfbvsdfgs@example.com',
                    ]
                ],
                [
                    '#user_registration_form_plainPassword_first',
                ]
            ],
        ];
    }
}
