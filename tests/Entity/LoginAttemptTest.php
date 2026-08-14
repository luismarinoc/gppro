<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\LoginAttempt;
use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoginAttempt::class)]
class LoginAttemptTest extends TestCase
{
    public function testDefaultState(): void
    {
        $sut = new LoginAttempt();

        self::assertNull($sut->getId());
        self::assertNull($sut->getUser());
        self::assertNull($sut->getAttemptedUsername());
        self::assertNull($sut->getIpAddress());
        self::assertNull($sut->getUserAgent());
        self::assertNull($sut->getOutcome());
        self::assertNull($sut->getFailureReason());
        self::assertNull($sut->getCreatedAt());
    }

    public function testSettersAndGettersWithUser(): void
    {
        $user = new User();
        $user->setUserIdentifier('john_doe');
        $createdAt = new \DateTimeImmutable('2026-08-14 10:00:00');

        $sut = new LoginAttempt();
        $sut->setUser($user);
        $sut->setAttemptedUsername('john_doe');
        $sut->setIpAddress('203.0.113.42');
        $sut->setUserAgent('Mozilla/5.0');
        $sut->setOutcome(LoginAttempt::OUTCOME_SUCCESS);
        $sut->setCreatedAt($createdAt);

        self::assertSame($user, $sut->getUser());
        self::assertSame('john_doe', $sut->getAttemptedUsername());
        self::assertSame('203.0.113.42', $sut->getIpAddress());
        self::assertSame('Mozilla/5.0', $sut->getUserAgent());
        self::assertSame(LoginAttempt::OUTCOME_SUCCESS, $sut->getOutcome());
        self::assertSame($createdAt, $sut->getCreatedAt());
        self::assertNull($sut->getFailureReason());
    }

    /**
     * Proves the `user` FK is nullable — the unknown-username case from the
     * spec must be representable without a User entity attached.
     */
    public function testUserIsNullableForUnknownUsername(): void
    {
        $sut = new LoginAttempt();
        $sut->setAttemptedUsername('does-not-exist');
        $sut->setOutcome(LoginAttempt::OUTCOME_FAILURE);
        $sut->setFailureReason('UserNotFoundException');

        self::assertNull($sut->getUser());
        self::assertSame('does-not-exist', $sut->getAttemptedUsername());
        self::assertSame(LoginAttempt::OUTCOME_FAILURE, $sut->getOutcome());
        self::assertSame('UserNotFoundException', $sut->getFailureReason());
    }

    public function testOutcomesConstantListsBothValues(): void
    {
        self::assertContains(LoginAttempt::OUTCOME_SUCCESS, LoginAttempt::OUTCOMES);
        self::assertContains(LoginAttempt::OUTCOME_FAILURE, LoginAttempt::OUTCOMES);
        self::assertCount(2, LoginAttempt::OUTCOMES);
    }
}
