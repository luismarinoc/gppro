<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Validator\Constraints;

use App\Entity\Milestone;
use App\Validator\Constraints\Milestone as MilestoneConstraint;
use App\Validator\Constraints\MilestoneValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<MilestoneValidator>
 */
#[CoversClass(\App\Validator\Constraints\Milestone::class)]
#[CoversClass(MilestoneValidator::class)]
class MilestoneValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): MilestoneValidator
    {
        return new MilestoneValidator();
    }

    public function testConstraintIsInvalid(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('foo', new NotBlank()); // @phpstan-ignore argument.type
    }

    public function testNonMilestoneValueIsIgnored(): void
    {
        $this->validator->validate(new \stdClass(), new MilestoneConstraint());

        $this->assertNoViolation();
    }

    public function testValueWithoutCurrencyRaisesViolationAtCurrency(): void
    {
        $milestone = new Milestone();
        $milestone->setValue('5000.0000');

        $this->validator->validate($milestone, new MilestoneConstraint());

        $this->buildViolation('A currency is required when a value is entered.')
            ->atPath('property.path.currency')
            ->setCode(MilestoneConstraint::MISSING_CURRENCY)
            ->assertRaised();
    }

    public function testCurrencyWithoutValueRaisesViolationAtValue(): void
    {
        $milestone = new Milestone();
        $milestone->setCurrency('USD');

        $this->validator->validate($milestone, new MilestoneConstraint());

        $this->buildViolation('A value is required when a currency is selected.')
            ->atPath('property.path.value')
            ->setCode(MilestoneConstraint::MISSING_VALUE)
            ->assertRaised();
    }

    public function testBothNullRaisesNoViolation(): void
    {
        $milestone = new Milestone();

        $this->validator->validate($milestone, new MilestoneConstraint());

        $this->assertNoViolation();
    }

    public function testBothSetRaisesNoViolation(): void
    {
        $milestone = new Milestone();
        $milestone->setValue('5000.0000');
        $milestone->setCurrency('USD');

        $this->validator->validate($milestone, new MilestoneConstraint());

        $this->assertNoViolation();
    }
}
