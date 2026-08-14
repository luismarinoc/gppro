<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\EventSubscriber;

use App\Entity\LoginAttempt;
use App\Entity\User;
use App\Repository\LoginAttemptRepository;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Persists a `LoginAttempt` audit row for every login (success and
 * failure), including attempts against unknown usernames.
 *
 * Sibling to `LastLoginSubscriber` (unchanged, still updates
 * `User::$lastLogin`) — this subscriber has a separate, additive
 * responsibility per design decision "Audit hook — event subscriber, not
 * UserChecker".
 */
final class LoginAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoginAttemptRepository $loginAttemptRepository,
        private readonly UserRepository $userRepository
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();

        $attempt = new LoginAttempt();
        $attempt->setUser($user instanceof User ? $user : null);
        $attempt->setAttemptedUsername($user->getUserIdentifier());
        $attempt->setIpAddress($request->getClientIp());
        $attempt->setUserAgent($this->getUserAgent($request));
        $attempt->setOutcome(LoginAttempt::OUTCOME_SUCCESS);
        $attempt->setCreatedAt(new \DateTimeImmutable());

        $this->loginAttemptRepository->saveLoginAttempt($attempt);
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        // Raw submitted `_username` POST field — no username-parameter
        // override configured in security.yaml (design decision).
        $attemptedUsername = (string) ($request->request->get('_username') ?? '');

        $user = null;
        if ($attemptedUsername !== '') {
            try {
                $loaded = $this->userRepository->loadUserByIdentifier($attemptedUsername);
                $user = $loaded instanceof User ? $loaded : null;
            } catch (UserNotFoundException) {
                $user = null;
            }
        }

        $attempt = new LoginAttempt();
        $attempt->setUser($user);
        $attempt->setAttemptedUsername($attemptedUsername);
        $attempt->setIpAddress($request->getClientIp());
        $attempt->setUserAgent($this->getUserAgent($request));
        $attempt->setOutcome(LoginAttempt::OUTCOME_FAILURE);
        // SECURITY: the short exception class name only — NEVER
        // getException()->getMessage(), which can leak sensitive detail
        // (e.g. hashes, internal identifiers) into the audit trail.
        $attempt->setFailureReason((new \ReflectionClass($event->getException()))->getShortName());
        $attempt->setCreatedAt(new \DateTimeImmutable());

        $this->loginAttemptRepository->saveLoginAttempt($attempt);
    }

    private function getUserAgent(Request $request): ?string
    {
        return $request->headers->get('User-Agent');
    }
}
