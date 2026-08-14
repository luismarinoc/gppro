<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller\Security;

use App\Configuration\SamlConfiguration;
use App\Configuration\SystemConfiguration;
use App\Controller\Security\SecurityController;
use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Tests\Configuration\TestConfigLoader;
use App\Tests\Controller\AbstractControllerBaseTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * This test makes sure the login and registration work as expected.
 * The logic is located in the FOSUserBundle and already tested, but we use a different layout.
 */
#[Group('integration')]
class SecurityControllerTest extends AbstractControllerBaseTestCase
{
    public function testRootUrlIsRedirectedToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        $this->assertIsRedirect($client, $this->createUrl('/homepage'));
        $client->followRedirect();
        $this->assertIsRedirect($client, $this->createUrl('/login'));
    }

    public function testLoginPageIsRendered(): void
    {
        $client = self::createClient();
        $this->request($client, '/login');

        $response = $client->getResponse();
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = $response->getContent();
        self::assertStringContainsString('<title>gppro</title>', $content);
        self::assertStringContainsString('<form action="/en/login_check" method="post"', $content);
        self::assertStringContainsString('<input autocomplete="username" type="text" id="username" name="_username"', $content);
        self::assertStringContainsString('<input autocomplete="new-password" id="password" name="_password" type="password"', $content);
        self::assertStringContainsString('">Log in</button>', $content);
        self::assertStringContainsString('<input type="hidden" name="_csrf_token" value="', $content);
        self::assertStringNotContainsString('<a href="/en/register/"', $content);
        self::assertStringNotContainsString('Register a new account', $content);
    }

    /**
     * Regression guard for the `always_remember_me` opt-in flip: the login
     * form must render a "Remember me" checkbox and it must be unchecked
     * by default (no `checked` attribute).
     */
    public function testLoginPageRendersRememberMeCheckboxUncheckedByDefault(): void
    {
        $client = self::createClient();
        $this->request($client, '/login');

        $response = $client->getResponse();
        self::assertTrue($response->isSuccessful());

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('name="_remember_me"', $content);
        self::assertStringContainsString('type="checkbox"', $content);

        $checkboxPosition = strpos($content, 'name="_remember_me"');
        self::assertIsInt($checkboxPosition);
        $checkboxTagStart = strrpos(substr($content, 0, $checkboxPosition), '<input');
        self::assertIsInt($checkboxTagStart);
        $checkboxTagEnd = strpos($content, '>', $checkboxPosition);
        self::assertIsInt($checkboxTagEnd);
        $checkboxTag = substr($content, $checkboxTagStart, $checkboxTagEnd - $checkboxTagStart + 1);
        self::assertStringNotContainsString('checked', $checkboxTag);
    }

    /**
     * `always_remember_me` must be `false`: logging in without checking
     * "Remember me" must NOT issue a persistent GPPRO_REMEMBER cookie.
     */
    public function testLoginWithoutRememberMeDoesNotIssuePersistentCookie(): void
    {
        $client = self::createClient();
        $this->request($client, '/login');

        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('body form')->form();
        $client->submit($form, [
            '_username' => UserFixtures::USERNAME_SUPER_ADMIN,
            '_password' => UserFixtures::DEFAULT_PASSWORD,
        ]);

        $this->assertIsRedirect($client);

        // Symfony's remember-me listener always emits a Set-Cookie header for
        // GPPRO_REMEMBER; when the checkbox was NOT ticked it sends a clearing
        // cookie (empty value, expiry in the past) rather than omitting the
        // header. "No persistent cookie" therefore means: absent, OR present
        // but already expired (i.e. not a persistent/future-dated cookie).
        $rememberMeCookie = $this->findCookie($client->getResponse()->headers->getCookies(), 'GPPRO_REMEMBER');
        if ($rememberMeCookie !== null) {
            self::assertLessThanOrEqual(time(), $rememberMeCookie->getExpiresTime(), 'The remember-me cookie must not be persistent (future-dated) when the checkbox is left unchecked.');
        }
    }

    /**
     * Opt-in: checking "Remember me" must still issue the persistent
     * GPPRO_REMEMBER cookie, with unchanged lifetime/security properties.
     */
    public function testLoginWithRememberMeCheckedIssuesPersistentCookie(): void
    {
        $client = self::createClient();
        $this->request($client, '/login');

        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('body form')->form();
        $client->submit($form, [
            '_username' => UserFixtures::USERNAME_SUPER_ADMIN,
            '_password' => UserFixtures::DEFAULT_PASSWORD,
            '_remember_me' => 'on',
        ]);

        $this->assertIsRedirect($client);

        $rememberMeCookie = $this->findCookie($client->getResponse()->headers->getCookies(), 'GPPRO_REMEMBER');
        self::assertNotNull($rememberMeCookie, 'A persistent remember-me cookie must be issued when the checkbox is checked.');
        self::assertTrue($rememberMeCookie->isHttpOnly());
        self::assertGreaterThan(time(), $rememberMeCookie->getExpiresTime());
    }

    /**
     * @param array<\Symfony\Component\HttpFoundation\Cookie> $cookies
     */
    private function findCookie(array $cookies, string $name): ?\Symfony\Component\HttpFoundation\Cookie
    {
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }

    public function testLoginPositive(): void
    {
        $client = self::createClient();
        $this->request($client, '/login');

        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('body form')->form();
        $client->submit($form, [
            '_username' => UserFixtures::USERNAME_SUPER_ADMIN,
            '_password' => UserFixtures::DEFAULT_PASSWORD
        ]);

        $this->assertIsRedirect($client); // redirect to root URL
        $client->followRedirect();

        $this->assertIsRedirect($client, '/homepage'); // redirect to homepage
        $client->followRedirect();

        $this->assertIsRedirect($client, '/timesheet/'); // redirect to configured start page
        $client->followRedirect();

        self::assertTrue($client->getResponse()->isSuccessful());
    }

    public function testLoginAlreadyLoggedIn(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $this->request($client, '/login');

        $this->assertIsRedirect($client, '/homepage'); // redirect to homepage
        $client->followRedirect();

        $this->assertIsRedirect($client, '/timesheet/'); // redirect to configured start page
        $client->followRedirect();

        self::assertTrue($client->getResponse()->isSuccessful());
    }

    public function testLoginNegative(): void
    {
        $client = self::createClient();
        $this->request($client, '/login');

        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('body form')->form();
        $client->submit($form, [
            '_username' => 'susan_super',
            '_password' => '1234567890'
        ]);

        $this->assertIsRedirect($client); // redirect to root URL
        $client->followRedirect();

        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertStringContainsString('<div class="alert alert-important alert-danger">Invalid credentials.</div>', $client->getResponse()->getContent());
    }

    public function testCheckAction(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You must configure the check path to be handled by the firewall using form_login in your security firewall configuration.');

        self::createClient(); // just to bootstrap the container
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $systemConfig = new SystemConfiguration(new TestConfigLoader([]), ['saml' => ['activate' => true]]);
        $samlConfig = new SamlConfiguration($systemConfig);
        $sut = new SecurityController($csrf, $samlConfig);
        $sut->checkAction();
    }

    public function testLogoutAction(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You must activate the logout in your security firewall configuration.');

        self::createClient(); // just to bootstrap the container
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $systemConfig = new SystemConfiguration(new TestConfigLoader([]), ['saml' => ['activate' => true]]);
        $samlConfig = new SamlConfiguration($systemConfig);
        $sut = new SecurityController($csrf, $samlConfig);
        $sut->logoutAction();
    }
}
