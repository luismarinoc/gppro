<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

#[Group('integration')]
class LayoutControllerTest extends AbstractControllerBaseTestCase
{
    public function testTeamleadIndicatorPresentForMembershipTeamlead(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $user = $this->getUserByRole(User::ROLE_USER);

        $this->makeTeamlead($user);

        $this->request($client, '/dashboard/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('data-teamlead-indicator="text">Teamlead</span>', $content);
        self::assertStringContainsString('data-teamlead-indicator="avatar"', $content);
    }

    public function testTeamleadIndicatorAbsentForPlainUser(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);

        $this->request($client, '/dashboard/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = (string) $client->getResponse()->getContent();

        self::assertStringNotContainsString('data-teamlead-indicator', $content);
    }

    public function testTeamleadIndicatorAbsentForGlobalRoleWithoutMembership(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $this->request($client, '/dashboard/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = (string) $client->getResponse()->getContent();

        self::assertStringNotContainsString('data-teamlead-indicator', $content);
    }

    public function testAdministrationIndicatorPresentForAdmin(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $this->request($client, '/dashboard/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('data-teamlead-indicator="text">Administration</span>', $content);
        self::assertStringContainsString('data-teamlead-indicator="avatar"', $content);
    }

    public function testAdministrationIndicatorTakesPriorityOverTeamlead(): void
    {
        // one role badge, not two stacked ones, when a user is both
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $user = $this->getUserByRole(User::ROLE_ADMIN);

        $this->makeTeamlead($user);

        $this->request($client, '/dashboard/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('data-teamlead-indicator="text">Administration</span>', $content);
        self::assertStringNotContainsString('data-teamlead-indicator="text">Teamlead</span>', $content);
    }

    public function testUserTitleStillRendersForTeamlead(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $this->request($client, '/dashboard/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('<div class="mt-1 small text-body-secondary">Head of Development</div>', $content);
    }

    private function makeTeamlead(User $user): void
    {
        $em = $this->getEntityManager();

        $team = new Team('Layout controller test team ' . uniqid());
        $em->persist($team);
        $em->flush();

        $member = new TeamMember();
        $member->setTeam($team);
        $member->setUser($user);
        $member->setTeamlead(true);
        $em->persist($member);
        $em->flush();
        $em->refresh($user);
    }

    public function testNavigationMenus(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);

        $user = $this->getUserByRole(User::ROLE_USER);

        $this->request($client, '/dashboard/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasMainHeader($client, $user);
        $this->assertHasNavigation($client);
    }

    protected function assertHasMainHeader(HttpKernelBrowser $client, User $user): void
    {
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('data-bs-toggle="dropdown" aria-label="Open personal menu"', $content);
        self::assertStringContainsString('href="/en/profile/' . $user->getUserIdentifier() . '"', $content);
        self::assertStringContainsString('href="/en/profile/' . $user->getUserIdentifier() . '/edit"', $content);
        self::assertStringContainsString('href="/en/profile/' . $user->getUserIdentifier() . '/prefs"', $content);
        self::assertStringContainsString('href="/en/logout', $content);
    }

    protected function assertHasNavigation(HttpKernelBrowser $client): void
    {
        $content = $client->getResponse()->getContent();

        self::assertStringContainsString('href="/en/dashboard/"', $content);
        self::assertStringContainsString('href="/en/timesheet/"', $content);
        self::assertStringContainsString('My times', $content);
        self::assertStringContainsString('href="/en/calendar/"', $content);
        self::assertStringContainsString('Calendar', $content);
    }

    public function testActiveEntries(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);

        $this->request($client, '/dashboard/');
        self::assertTrue($client->getResponse()->isSuccessful());

        $content = $client->getResponse()->getContent();

        self::assertStringContainsString('<a title="Start time-tracking" href="/en/timesheet/create" class="modal-ajax-form ticktac-start btn', $content);
    }
}
