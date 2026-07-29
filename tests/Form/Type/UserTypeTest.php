<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Form\Type;

use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\UserType as UserCategory;
use App\Form\Type\UserType;
use App\Repository\RolePermissionRepository;
use App\Repository\UserRepository;
use App\Security\RolePermissionManager;
use App\User\PermissionService;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Unit-tests UserType::configureOptions()'s 'choices' resolution directly
 * against a bare OptionsResolver, bypassing the full Form component / the
 * EntityType/Doctrine-bridge parent chain (which needs a real
 * ManagerRegistry and is out of scope for this filter logic). The 'user'
 * option, normally injected by the global App\Form\Extension\UserExtension
 * form-type extension, is defined manually here to keep this test isolated.
 */
#[CoversClass(UserType::class)]
class UserTypeTest extends TestCase
{
    private UserRepository&MockObject $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function createPermissionManager(): RolePermissionManager
    {
        $repository = $this->getMockBuilder(RolePermissionRepository::class)
            ->onlyMethods(['getAllAsArray'])
            ->disableOriginalConstructor()
            ->getMock();
        $repository->method('getAllAsArray')->willReturn([]);

        /* @var RolePermissionRepository $repository */
        return new RolePermissionManager(new PermissionService($repository, new ArrayAdapter()), [], []);
    }

    private static function userWithId(int $id, ?UserCategory $userType = null): User
    {
        $user = new User();
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($user, $id);

        if (null !== $userType) {
            $user->setUserType($userType);
        }

        return $user;
    }

    /**
     * @param User[] $users
     */
    private function stubUsers(array $users): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($users);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getQuery')->willReturn($query);

        $this->userRepository->method('getQueryBuilderForFormType')->willReturn($qb);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<int, User>
     */
    private function resolveChoices(array $options): array
    {
        $type = new UserType($this->userRepository, $this->createPermissionManager());

        $resolver = new OptionsResolver();
        // normally provided by App\Form\Extension\UserExtension
        $resolver->setDefined('user');
        $resolver->setDefault('user', new User());

        $type->configureOptions($resolver);

        $resolved = $resolver->resolve($options);

        /** @var array<int, User> $choices */
        $choices = $resolved['choices'];

        return $choices;
    }

    public function testExcludesTechnicalUserWithoutProjectAccessFromChoices(): void
    {
        $withoutAccess = self::userWithId(1, UserCategory::TECHNICAL);
        $this->stubUsers([$withoutAccess]);

        // a project with a team the candidate is NOT part of denies project access
        $project = new Project();
        $project->setCustomer(new Customer('Acme'));
        $team = new Team('Project team');
        $project->addTeam($team);

        $choices = $this->resolveChoices([
            'user_type' => UserCategory::TECHNICAL,
            'project' => $project,
        ]);

        self::assertSame([], $choices);
    }

    public function testIncludesTechnicalUserWithProjectAccessInChoices(): void
    {
        $withAccess = self::userWithId(2, UserCategory::TECHNICAL);
        $this->stubUsers([$withAccess]);

        // a project without any restricting teams grants open access
        $project = new Project();
        $project->setCustomer(new Customer('Acme'));

        $choices = $this->resolveChoices([
            'user_type' => UserCategory::TECHNICAL,
            'project' => $project,
        ]);

        self::assertCount(1, $choices);
        self::assertSame($withAccess, reset($choices));
    }

    public function testIncludeUsersStillWinsOverProjectAccessFilter(): void
    {
        $includedWithoutAccess = self::userWithId(3, UserCategory::TECHNICAL);
        $this->stubUsers([$includedWithoutAccess]);

        $project = new Project();
        $project->setCustomer(new Customer('Acme'));
        $team = new Team('Project team');
        $project->addTeam($team);

        $choices = $this->resolveChoices([
            'user_type' => UserCategory::TECHNICAL,
            'project' => $project,
            'include_users' => [$includedWithoutAccess],
        ]);

        self::assertCount(1, $choices);
        self::assertSame($includedWithoutAccess, reset($choices));
    }
}
