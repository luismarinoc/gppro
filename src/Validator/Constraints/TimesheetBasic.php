<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Validator\Constraints;

final class TimesheetBasic extends TimesheetConstraint
{
    public const MISSING_BEGIN_ERROR = 'gppro-timesheet-81';
    public const END_BEFORE_BEGIN_ERROR = 'gppro-timesheet-82';
    public const MISSING_ACTIVITY_ERROR = 'gppro-timesheet-84';
    public const MISSING_PROJECT_ERROR = 'gppro-timesheet-85';
    public const ACTIVITY_PROJECT_MISMATCH_ERROR = 'gppro-timesheet-86';
    public const PROJECT_NOT_STARTED = 'gppro-timesheet-91';
    public const PROJECT_ALREADY_ENDED = 'gppro-timesheet-92';
    public const PROJECT_DISALLOWS_GLOBAL_ACTIVITY = 'gppro-timesheet-93';

    protected const ERROR_NAMES = [
        self::MISSING_BEGIN_ERROR => 'You must submit a begin date.',
        self::END_BEFORE_BEGIN_ERROR => 'End date must not be earlier then start date.',
        self::MISSING_ACTIVITY_ERROR => 'An activity needs to be selected.',
        self::MISSING_PROJECT_ERROR => 'A project needs to be selected.',
        self::ACTIVITY_PROJECT_MISMATCH_ERROR => 'Project mismatch, project specific activity and timesheet project are different.',
        self::PROJECT_NOT_STARTED => 'The project has not started at that time.',
        self::PROJECT_ALREADY_ENDED => 'The project is finished at that time.',
        self::PROJECT_DISALLOWS_GLOBAL_ACTIVITY => 'Global activities are forbidden for the selected project.',
    ];

    public string $message = 'This timesheet has invalid settings.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
