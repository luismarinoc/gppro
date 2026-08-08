<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App;

/**
 * Some "very" global constants for Kimai.
 */
final class Constants
{
    /**
     * The current release version
     */
    public const VERSION = '2.62.40';
    /**
     * The current release: major * 10000 + minor * 100 + patch
     */
    public const VERSION_ID = 26240;
    /**
     * The software name
     */
    public const SOFTWARE = 'gppro';
    /**
     * Used in multiple views
     */
    public const GITHUB = 'https://github.com/tbema/gppro/';
    /**
     * The GitHub repository name
     */
    public const GITHUB_REPO = 'tbema/gppro';
    /**
     * Homepage, used in multiple views
     */
    public const HOMEPAGE = 'https://github.com/tbema/gppro';
    /**
     * Default color for Customer, Project and Activity entities
     */
    public const DEFAULT_COLOR = '#d2d6de';
}
