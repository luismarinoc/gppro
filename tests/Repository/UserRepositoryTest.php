<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\Query\UserQuery;
use App\Repository\Query\VisibilityInterface;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(UserRepository::class)]
#[Group('integration')]
class UserRepositoryTest extends AbstractRepositoryTestCase
{
    private function getRepository(): UserRepository
    {
        /** @var UserRepository $repository */
        $repository = $this->getEntityManager()->getRepository(User::class);

        return $repository;
    }

    private function persistUser(string $username, string $email, bool $enabled, ?\DateTimeImmutable $emailConfirmedAt, ?\DateTimeImmutable $rejectedAt): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setLanguage('en');
        $user->setEnabled($enabled);
        $user->setPassword('irrelevant-hash-not-used-in-this-test');
        $user->setEmailConfirmedAt($emailConfirmedAt);
        $user->setRejectedAt($rejectedAt);

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        return $user;
    }

    public function testPendingApprovalFilterReturnsOnlyEmailConfirmedDisabledNotRejectedUsers(): void
    {
        $pending = $this->persistUser('pendingqrx', 'pendingqrx@example.com', false, new \DateTimeImmutable('-1 hour'), null);
        $neverConfirmed = $this->persistUser('neverconfirmedqrx', 'neverconfirmedqrx@example.com', false, null, null);
        $rejected = $this->persistUser('rejectedqrx', 'rejectedqrx@example.com', false, new \DateTimeImmutable('-1 day'), new \DateTimeImmutable('-1 hour'));
        $approved = $this->persistUser('approvedqrx', 'approvedqrx@example.com', true, new \DateTimeImmutable('-1 day'), null);

        $query = new UserQuery();
        $query->setVisibility(VisibilityInterface::SHOW_BOTH);
        $query->setPendingApproval(true);

        $result = $this->getRepository()->getUsersForQuery($query);
        $resultIds = array_map(static fn (User $u): ?int => $u->getId(), $result);

        self::assertContains($pending->getId(), $resultIds, 'The pending user must be included.');
        self::assertNotContains($neverConfirmed->getId(), $resultIds, 'A never-confirmed user must be excluded even though enabled=false.');
        self::assertNotContains($rejected->getId(), $resultIds, 'A rejected user must be excluded.');
        self::assertNotContains($approved->getId(), $resultIds, 'An approved (enabled) user must be excluded.');
    }
}
