<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\EventSubscriber\Actions;

use App\Entity\User;
use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\UserSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(UserSubscriber::class)]
class UserSubscriberTest extends AbstractActionsSubscriberTestCase
{
    public function testEventName(): void
    {
        $this->assertGetSubscribedEvent(UserSubscriber::class, 'user');
    }

    private function createTargetUser(int $id): User
    {
        $user = new User();
        $idProperty = new \ReflectionProperty(User::class, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, $id);
        $user->setUsername('user' . $id);
        $user->setEnabled(true);

        return $user;
    }

    /**
     * @param string[] $grantedAttributes
     */
    private function onActionsFor(User $targetUser, array $grantedAttributes): PageActionsEvent
    {
        $actingUser = $this->createTargetUser(1);

        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturnCallback(
            static fn (string $attribute, mixed $subject = null): bool => \in_array($attribute, $grantedAttributes, true)
        );

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => $route . '?' . http_build_query($parameters)
        );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->willReturnCallback(
            static fn (string $id): CsrfToken => new CsrfToken($id, 'fake-token-for-' . $id)
        );

        $subscriber = new UserSubscriber($auth, $router, $eventDispatcher, $csrfTokenManager);

        $event = new PageActionsEvent($actingUser, ['user' => $targetUser], 'user', 'index');
        $subscriber->onActions($event);

        return $event;
    }

    public function testOnActionsAddsSecuritySubmenuWithBothActionsWhenPasswordGranted(): void
    {
        $targetUser = $this->createTargetUser(2);

        $event = $this->onActionsFor($targetUser, ['password']);

        self::assertTrue($event->hasSubmenu('security'));
        self::assertEquals(2, $event->countActions('security'));

        $children = $event->getActions()['security']['children'];
        self::assertIsArray($children);

        self::assertArrayHasKey('force_password_reset', $children);
        self::assertEquals('force_password_reset', $children['force_password_reset']['title']);
        self::assertStringContainsString('admin_user_force_password_reset', $children['force_password_reset']['url']);
        self::assertStringContainsString('id=2', $children['force_password_reset']['url']);
        self::assertStringContainsString('csrfToken=fake-token-for-admin_user_force_password_reset_2', $children['force_password_reset']['url']);

        self::assertArrayHasKey('revoke_remember_me', $children);
        self::assertEquals('revoke_remember_me', $children['revoke_remember_me']['title']);
        self::assertStringContainsString('admin_user_revoke_remember_me', $children['revoke_remember_me']['url']);
        self::assertStringContainsString('id=2', $children['revoke_remember_me']['url']);
        self::assertStringContainsString('csrfToken=fake-token-for-admin_user_revoke_remember_me_2', $children['revoke_remember_me']['url']);
    }

    public function testOnActionsOmitsSecuritySubmenuWhenPasswordNotGranted(): void
    {
        $targetUser = $this->createTargetUser(2);

        // no 'password' attribute in the granted list at all - simulates a
        // non-admin viewer for whom UserVoter denies the 'password' attribute
        $event = $this->onActionsFor($targetUser, ['view']);

        self::assertFalse($event->hasSubmenu('security'));
        self::assertEquals(0, $event->countActions('security'));
    }
}
