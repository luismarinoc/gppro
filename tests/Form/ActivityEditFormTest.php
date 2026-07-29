<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Form;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\User;
use App\Form\ActivityEditForm;
use App\Form\Extension\UserExtension;
use App\Form\Type\UserType;
use App\Repository\ActivityBoardStateRepository;
use App\Repository\RolePermissionRepository;
use App\Repository\UserRepository;
use App\Security\RolePermissionManager;
use App\User\PermissionService;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Test\TypeTestCase;

#[CoversClass(ActivityEditForm::class)]
class ActivityEditFormTest extends TypeTestCase
{
    /**
     * @return FormTypeInterface[]
     */
    protected function getTypes(): array // @phpstan-ignore missingType.generics
    {
        $boardStateRepository = $this->createMock(ActivityBoardStateRepository::class);
        $boardStateRepository->method('findByActivities')->willReturn([]);

        $userRepository = $this->createMock(UserRepository::class);
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getQuery')->willReturn($query);
        $userRepository->method('getQueryBuilderForFormType')->willReturn($queryBuilder);

        $permissionRepository = $this->getMockBuilder(RolePermissionRepository::class)
            ->onlyMethods(['getAllAsArray'])
            ->disableOriginalConstructor()
            ->getMock();
        $permissionRepository->method('getAllAsArray')->willReturn([]);
        /** @var RolePermissionRepository $permissionRepository */
        $permissionManager = new RolePermissionManager(new PermissionService($permissionRepository, new ArrayAdapter()), [], []);

        // EntityType (UserType's parent) resolves 'em'/'id_reader' eagerly during
        // option resolution even though UserType always supplies 'choices'
        // explicitly - wire up the minimal Doctrine metadata chain it needs.
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getIdentifierFieldNames')->willReturn(['id']);
        $classMetadata->method('getTypeOfField')->willReturn('integer');
        $classMetadata->method('hasAssociation')->willReturn(false);

        $objectManager = $this->createMock(ObjectManager::class);
        $objectManager->method('getClassMetadata')->willReturn($classMetadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($objectManager);

        return [
            new ActivityEditForm($boardStateRepository),
            new UserType($userRepository, $permissionManager),
            new EntityType($registry),
        ];
    }

    /**
     * @return FormTypeExtensionInterface[]
     */
    // @phpstan-ignore missingType.generics
    protected function getTypeExtensions(): array
    {
        $auth = $this->createMock(Security::class);
        $auth->method('getUser')->willReturn(new User());

        return [
            new UserExtension($auth),
        ];
    }

    public function testWithGlobalNewActivity(): void
    {
        $model = new Activity();
        $form = $this->factory->createBuilder(ActivityEditForm::class, $model);

        $attr = $form->getFormConfig()->getOption('attr');
        self::assertArrayHasKey('data-form-event', $attr);
        self::assertEquals('gppro.activityUpdate', $attr['data-form-event']);

        self::assertTrue($form->has('name'));
        self::assertTrue($form->has('comment'));
        self::assertTrue($form->has('project'));
        self::assertTrue($form->has('color'));
        self::assertTrue($form->has('metaFields'));
        self::assertTrue($form->has('visible'));
        self::assertFalse($form->has('budget'));
        self::assertFalse($form->has('timeBudget'));
        self::assertFalse($form->has('budgetType'));
        self::assertFalse($form->has('technicalUser'));
        self::assertFalse($form->has('functionalUser'));
    }

    public function testWithGlobalNewActivityAndOptionsBudget(): void
    {
        $model = new Activity();
        $form = $this->factory->createBuilder(ActivityEditForm::class, $model, [
            'include_budget' => true,
        ]);
        self::assertTrue($form->has('budget'));
        self::assertFalse($form->has('timeBudget'));
        self::assertTrue($form->has('budgetType'));
    }

    public function testWithGlobalNewActivityAndOptionsTimeBudget(): void
    {
        $model = new Activity();
        $form = $this->factory->createBuilder(ActivityEditForm::class, $model, [
            'include_time' => true,
        ]);
        self::assertFalse($form->has('budget'));
        self::assertTrue($form->has('timeBudget'));
        self::assertTrue($form->has('budgetType'));
    }

    public function testWithGlobalNewActivityAndOptionsAllBudget(): void
    {
        $model = new Activity();
        $form = $this->factory->createBuilder(ActivityEditForm::class, $model, [
            'include_budget' => true,
            'include_time' => true,
        ]);
        self::assertTrue($form->has('budget'));
        self::assertTrue($form->has('timeBudget'));
        self::assertTrue($form->has('budgetType'));
    }

    public function testWithGlobalExistingActivityAndOptions(): void
    {
        $model = $this->createMock(Activity::class);
        $model->expects($this->once())->method('getId')->willReturn(1);
        $model->expects($this->atLeast(1))->method('isGlobal')->willReturn(true);
        $form = $this->factory->createBuilder(ActivityEditForm::class, $model, [
            'include_budget' => true,
        ]);
        self::assertFalse($form->has('project'));
        self::assertTrue($form->has('budget'));
        self::assertFalse($form->has('timeBudget'));
        self::assertFalse($form->has('technicalUser'));
        self::assertFalse($form->has('functionalUser'));
    }

    public function testWithNonGlobalExistingActivityAndOptions(): void
    {
        $project = new Project();
        $customer = new Customer('foo');
        $project->setCustomer($customer);
        $model = $this->createMock(Activity::class);

        $model->expects($this->any())->method('getId')->willReturn(1);
        $model->expects($this->any())->method('getProject')->willReturn($project);
        $form = $this->factory->createBuilder(ActivityEditForm::class, $model, [
            'include_budget' => true,
            'include_time' => true,
        ]);
        self::assertTrue($form->has('name'));
        self::assertTrue($form->has('comment'));
        self::assertTrue($form->has('project'));
        self::assertTrue($form->has('color'));
        self::assertTrue($form->has('metaFields'));
        self::assertTrue($form->has('visible'));
        self::assertTrue($form->has('project'));
        self::assertTrue($form->has('budget'));
        self::assertTrue($form->has('timeBudget'));
        self::assertTrue($form->has('technicalUser'));
        self::assertTrue($form->has('functionalUser'));

        self::assertSame($project, $form->get('technicalUser')->getOption('project'));
        self::assertSame($project, $form->get('functionalUser')->getOption('project'));
    }
}
