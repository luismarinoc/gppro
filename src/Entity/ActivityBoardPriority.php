<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

enum ActivityBoardPriority: string
{
    case URGENT = 'urgent';
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';

    /**
     * Column-ordering weight: Urgent sorts first (0), Low sorts last among
     * defined priorities (3), and no priority at all sorts after every
     * defined priority (4). Static because $priority may be null - a card
     * without a priority has no enum instance to call an instance method on.
     */
    public static function sortWeight(?self $priority): int
    {
        return match ($priority) {
            self::URGENT => 0,
            self::HIGH => 1,
            self::MEDIUM => 2,
            self::LOW => 3,
            null => 4,
        };
    }
}
