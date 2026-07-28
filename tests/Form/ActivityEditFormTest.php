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
use App\Form\ActivityEditForm;
use App\Form\Type\UserType;
use App\Repository\ActivityBoardStateRepository;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
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

        return [
            new ActivityEditForm($boardStateRepository),
            new UserType($userRepository),
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
    }
}
