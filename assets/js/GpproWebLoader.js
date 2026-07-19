/*
 * This file is part of the Kimai time-tracking app.
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
            // NOTE: intentionally kept as the legacy global name "KimaiWebLoader" — it is the
            // external ABI consumed by templates/macros/webloader.html.twig ("new KimaiWebLoader(...)"),
            // which is explicitly out of scope for this PR (deferred to chunk 5b, window.kimai rename).
            return (root.KimaiWebLoader = factory());
        });
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        // NOTE: same external-ABI exception as above.
        root.KimaiWebLoader = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {

    class GpproWebLoader extends GpproLoader {
    }

    return GpproWebLoader;

}));
