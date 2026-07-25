<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\FxRate;

/**
 * Per-indicator outcome of an {@see FxRateSynchronizer} run. Lets the
 * console command (PR3) map results to the three product-defined exit
 * cases: success with data, success without data, and real failure.
 */
enum FxRateSyncStatus
{
    /**
     * A new or forced value was fetched and persisted.
     */
    case PERSISTED;

    /**
     * A row already existed and --force was not passed; left untouched.
     */
    case SKIPPED_EXISTING;

    /**
     * The API answered but has no value for this date (weekend/holiday).
     */
    case SKIPPED_NO_DATA;

    /**
     * A real failure: transport error, non-404 non-2xx status, or malformed JSON.
     */
    case FAILED;
}
