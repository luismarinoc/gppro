/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] GpproEscape: sanitize strings
 */

import GpproPlugin from "../GpproPlugin";
import DOMPurify from "dompurify";

export default class GpproEscape extends GpproPlugin {

    getId() {
        return 'escape';
    }

    /**
     * @param {string} title
     * @returns {string}
     */
    escapeForHtml(title) {
        if (title === undefined || title === null) {
            return '';
        }

        const charToReplace = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
        };

        return title.replace(/[&<>"]/g, function(tag) {
            return charToReplace[tag] || tag;
        });
    }

    /**
     * @param {string} html
     * @returns {string}
     */
    sanitize(html) {
        return DOMPurify.sanitize(html);
    }
}
