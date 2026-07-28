<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

enum UserType: string
{
    case FUNCTIONAL = 'functional';
    case TECHNICAL = 'technical';
    case GUEST = 'guest';
}
