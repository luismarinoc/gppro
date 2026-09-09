/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [GPPRO] GpproAlternativeLinks
 *
 * allows to assign the given selector to any element, which then is used as click-handler
 * redirecting to the URL given in the elements 'data-href' or 'href' attribute
 */

import GpproReducedClickHandler from "./GpproReducedClickHandler";

export default class GpproAlternativeLinks extends GpproReducedClickHandler {

    constructor(selector) {
        super();
        this._selector = selector;
    }

    init() {
        const navigate = function(href) {
            const destination = new URL(href, window.location.origin);
            if (destination.origin !== window.location.origin) {
                return;
            }

            window.location.assign(destination.pathname + destination.search + destination.hash);
        };

        this.addClickHandler(this._selector, navigate, []);
        this.addRowLinkKeydownHandler(navigate);
    }

    addRowLinkKeydownHandler(callback) {
        if (this._rowLinkKeydownHandlerInitialized) {
            return;
        }

        this._rowLinkKeydownHandlerInitialized = true;
        document.body.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey || !(event.target instanceof Element)) {
                return;
            }

            const row = event.target.closest('[data-gp-row-link]');
            if (row === null) {
                return;
            }

            const interactiveElement = event.target.closest('a, button, input, select, textarea, label, [role="button"], [role="link"], [role="menu"], [role="menubar"], [role^="menuitem"], [contenteditable]');
            if (interactiveElement !== null && interactiveElement !== row) {
                return;
            }

            const href = row.dataset.href || row.getAttribute('href');
            if (href === null || href === '') {
                return;
            }

            event.preventDefault();
            callback(href);
        });
    }

}
