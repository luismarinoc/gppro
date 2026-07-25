<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Milestone extends Constraint
{
    public const MISSING_CURRENCY = 'gppro-milestone-001';
    public const MISSING_VALUE = 'gppro-milestone-002';

    protected const ERROR_NAMES = [
        self::MISSING_CURRENCY => 'A currency is required when a value is entered.',
        self::MISSING_VALUE => 'A value is required when a currency is selected.',
    ];

    public string $message = 'The milestone has invalid settings.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
