/*
 * This file is part of the gppro time-tracking app.
 *
 * Main JS application file for Kimai 2. This file should be included in all pages.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] Wrapper class for loading Kimai app in browser script scope
 */

import GpproLoader from "./GpproLoader";

(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], function () {
            // Exposed as the global "GpproWebLoader", consumed by
            // templates/macros/webloader.html.twig ("new GpproWebLoader(...)").
            return (root.GpproWebLoader = factory());
        });
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.GpproWebLoader = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {

    class GpproWebLoader extends GpproLoader {
    }

    return GpproWebLoader;

}));
