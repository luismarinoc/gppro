/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] GpproAlternativeLinks
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
        this.addClickHandler(this._selector, function(href) {
            window.location = href;
        }, []);
    }

}
