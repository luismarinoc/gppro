<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\EventSubscriber;

use App\Entity\LoginAttempt;
use App\Entity\User;
use App\EventSubscriber\LoginAuditSubscriber;
use App\Repository\LoginAttemptRepository;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[CoversClass(LoginAuditSubscriber::class)]
class LoginAuditSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = LoginAuditSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(LoginSuccessEvent::class, $events);
        self::assertArrayHasKey(LoginFailureEvent::class, $events);
    }

    public function testOnLoginSuccessPersistsLoginAttempt(): void
    {
        $user = new User();
        $user->setUsername('john_doe');

        $loginAttemptRepository = $this->createMock(LoginAttemptRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->never())->method('loadUserByIdentifier');

        $captured = null;
        $loginAttemptRepository->expects($this->once())
            ->method('saveLoginAttempt')
            ->with($this->callback(function (LoginAttempt $attempt) use (&$captured): bool {
                $captured = $attempt;

                return true;
            }));

        $sut = new LoginAuditSubscriber($loginAttemptRepository, $userRepository);

        $request = Request::create('/login_check', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.5',
            'HTTP_USER_AGENT' => 'TestAgent/1.0',
        ]);

        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $passport = $this->createMock(Passport::class);
        $passport->method('getUser')->willReturn($user);

        $event = new LoginSuccessEvent($authenticator, $passport, new UsernamePasswordToken($user, 'main'), $request, null, 'main');

        $sut->onLoginSuccess($event);

        self::assertInstanceOf(LoginAttempt::class, $captured);
        self::assertSame($user, $captured->getUser());
        self::assertSame('john_doe', $captured->getAttemptedUsername());
        self::assertSame('203.0.113.5', $captured->getIpAddress());
        self::assertSame('TestAgent/1.0', $captured->getUserAgent());
        self::assertSame(LoginAttempt::OUTCOME_SUCCESS, $captured->getOutcome());
        self::assertNull($captured->getFailureReason());
        self::assertNotNull($captured->getCreatedAt());
    }

    public function testOnLoginFailureWithKnownUsernamePersistsLoginAttemptWithUser(): void
    {
        $user = new User();
        $user->setUsername('known_user');

        $loginAttemptRepository = $this->createMock(LoginAttemptRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with('known_user')
            ->willReturn($user);

        $captured = null;
        $loginAttemptRepository->expects($this->once())
            ->method('saveLoginAttempt')
            ->with($this->callback(function (LoginAttempt $attempt) use (&$captured): bool {
                $captured = $attempt;

                return true;
            }));

        $sut = new LoginAuditSubscriber($loginAttemptRepository, $userRepository);

        $request = Request::create('/login_check', 'POST', ['_username' => 'known_user'], [], [], [
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_USER_AGENT' => 'TestAgent/2.0',
        ]);

        $exception = new BadCredentialsException('Sensitive internal detail that must never leak.');
        $authenticator = $this->createMock(AuthenticatorInterface::class);

        $event = new LoginFailureEvent($exception, $authenticator, $request, null, 'main', null);

        $sut->onLoginFailure($event);

        self::assertInstanceOf(LoginAttempt::class, $captured);
        self::assertSame($user, $captured->getUser());
        self::assertSame('known_user', $captured->getAttemptedUsername());
        self::assertSame(LoginAttempt::OUTCOME_FAILURE, $captured->getOutcome());
        self::assertSame('BadCredentialsException', $captured->getFailureReason());
    }

    public function testOnLoginFailureWithUnknownUsernamePersistsLoginAttemptWithNullUser(): void
    {
        $loginAttemptRepository = $this->createMock(LoginAttemptRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())
            ->method('loadUserByIdentifier')
            ->with('ghost_user')
            ->willThrowException(new UserNotFoundException());

        $captured = null;
        $loginAttemptRepository->expects($this->once())
            ->method('saveLoginAttempt')
            ->with($this->callback(function (LoginAttempt $attempt) use (&$captured): bool {
                $captured = $attempt;

                return true;
            }));

        $sut = new LoginAuditSubscriber($loginAttemptRepository, $userRepository);

        $request = Request::create('/login_check', 'POST', ['_username' => 'ghost_user']);

        $exception = new UserNotFoundException();
        $authenticator = $this->createMock(AuthenticatorInterface::class);

        $event = new LoginFailureEvent($exception, $authenticator, $request, null, 'main', null);

        $sut->onLoginFailure($event);

        self::assertInstanceOf(LoginAttempt::class, $captured);
        self::assertNull($captured->getUser());
        self::assertSame('ghost_user', $captured->getAttemptedUsername());
        self::assertSame(LoginAttempt::OUTCOME_FAILURE, $captured->getOutcome());
        self::assertSame('UserNotFoundException', $captured->getFailureReason());
    }

    /**
     * Security-critical: proves `failureReason` is derived from the
     * exception's SHORT class name, never from `getMessage()` — a raw
     * message can leak sensitive detail (design's flagged risk).
     */
    public function testFailureReasonNeverContainsRawExceptionMessage(): void
    {
        $loginAttemptRepository = $this->createMock(LoginAttemptRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('loadUserByIdentifier')->willThrowException(new UserNotFoundException());

        $captured = null;
        $loginAttemptRepository->method('saveLoginAttempt')
            ->with($this->callback(function (LoginAttempt $attempt) use (&$captured): bool {
                $captured = $attempt;

                return true;
            }));

        $sut = new LoginAuditSubscriber($loginAttemptRepository, $userRepository);

        $sensitiveMessage = 'Password hash mismatch for admin@internal.example with stored bcrypt digest $2y$...secret';
        $exception = new BadCredentialsException($sensitiveMessage);
        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $request = Request::create('/login_check', 'POST', ['_username' => 'someone']);

        $event = new LoginFailureEvent($exception, $authenticator, $request, null, 'main', null);
        $sut->onLoginFailure($event);

        self::assertInstanceOf(LoginAttempt::class, $captured);
        self::assertSame('BadCredentialsException', $captured->getFailureReason());
        self::assertStringNotContainsString('admin@internal.example', (string) $captured->getFailureReason());
        self::assertStringNotContainsString($sensitiveMessage, (string) $captured->getFailureReason());
        self::assertNotSame($exception->getMessage(), $captured->getFailureReason());
    }
}
