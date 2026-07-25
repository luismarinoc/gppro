<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Validator\Constraints;

use App\Entity\Milestone as MilestoneEntity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class MilestoneValidator extends ConstraintValidator
{
    /**
     * @param MilestoneEntity $value
     * @param Constraint $constraint
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!($constraint instanceof Milestone)) {
            throw new UnexpectedTypeException($constraint, Milestone::class);
        }

        if (!\is_object($value) || !($value instanceof MilestoneEntity)) {
            return;
        }

        if ($value->getValue() !== null && $value->getCurrency() === null) {
            $this->context->buildViolation(Milestone::getErrorName(Milestone::MISSING_CURRENCY))
                ->atPath('currency')
                ->setTranslationDomain('validators')
                ->setCode(Milestone::MISSING_CURRENCY)
                ->addViolation();

            return;
        }

        if ($value->getCurrency() !== null && $value->getValue() === null) {
            $this->context->buildViolation(Milestone::getErrorName(Milestone::MISSING_VALUE))
                ->atPath('value')
                ->setTranslationDomain('validators')
                ->setCode(Milestone::MISSING_VALUE)
                ->addViolation();
        }
    }
}
