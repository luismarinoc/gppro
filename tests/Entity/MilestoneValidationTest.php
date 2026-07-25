<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\Milestone;
use App\Entity\Project;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(Milestone::class)]
#[Group('integration')]
class MilestoneValidationTest extends KernelTestCase
{
    use EntityValidationTestTrait;

    private function getEntity(): Milestone
    {
        $entity = new Milestone();
        $entity->setProject(new Project());
        $entity->setName('Test Milestone');

        return $entity;
    }

    public function testValidationCurrencyWithoutValueIsRejected(): void
    {
        $entity = $this->getEntity();
        $entity->setCurrency('USD');

        $this->assertHasViolationForField($entity, 'value');
    }

    public function testValidationValueWithoutCurrencyIsRejected(): void
    {
        $entity = $this->getEntity();
        $entity->setValue('100.0000');

        $this->assertHasViolationForField($entity, 'currency');
    }

    public function testValidationZeroValueIsRejected(): void
    {
        $entity = $this->getEntity();
        $entity->setValue('0');
        $entity->setCurrency('USD');

        $this->assertHasViolationForField($entity, 'value');
    }

    public function testValidationNegativeValueIsRejected(): void
    {
        $entity = $this->getEntity();
        $entity->setValue('-10.0000');
        $entity->setCurrency('USD');

        $this->assertHasViolationForField($entity, 'value');
    }

    public function testValidationInvalidCurrencyCodeIsRejected(): void
    {
        $entity = $this->getEntity();
        $entity->setValue('100.0000');
        $entity->setCurrency('XXXXX');

        $this->assertHasViolationForField($entity, 'currency');
    }

    public function testValidationUnsupportedCurrencyIsRejected(): void
    {
        $entity = $this->getEntity();
        $entity->setValue('100.0000');
        $entity->setCurrency('EUR');

        $this->assertHasViolationForField($entity, 'currency');
    }

    public function testValidationSupportedCurrenciesAreAccepted(): void
    {
        foreach (Milestone::SUPPORTED_CURRENCIES as $currency) {
            $entity = $this->getEntity();
            $entity->setValue('100.0000');
            $entity->setCurrency($currency);

            $this->assertHasNoViolations($entity);
        }
    }

    public function testValidationMinimumPositiveValueIsAccepted(): void
    {
        $entity = $this->getEntity();
        $entity->setValue('0.01');
        $entity->setCurrency('USD');

        $this->assertHasNoViolations($entity);
    }

    public function testValidationBothNullIsAccepted(): void
    {
        $entity = $this->getEntity();

        $this->assertHasNoViolations($entity);
    }
}
