/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] GpproForm: basic functions for all forms
 */

import GpproPlugin from "../GpproPlugin";
import GpproFormPlugin from "../forms/GpproFormPlugin";

export default class GpproForm extends GpproPlugin {

    getId()
    {
        return 'form';
    }

    activateForm(formSelector)
    {
        [].slice.call(document.querySelectorAll(formSelector)).map((form) => {
            for (const plugin of this.getContainer().getPlugins()) {
                if (plugin instanceof GpproFormPlugin && plugin.supportsForm(form)) {
                    plugin.activateForm(form);
                }
            }
        });
    }

    destroyForm(formSelector)
    {
        [].slice.call(document.querySelectorAll(formSelector)).map((form) => {
            for (const plugin of this.getContainer().getPlugins()) {
                if (plugin instanceof GpproFormPlugin && plugin.supportsForm(form)) {
                    plugin.destroyForm(form);
                }
            }
        });
    }

    /**
     * @param {HTMLFormElement} form
     * @param {Object} overwrites
     * @param {boolean} removeEmpty
     * @returns {string}
     */
    convertFormDataToQueryString(form, overwrites = {}, removeEmpty = false)
    {
        let serialized = [];
        let data = new FormData(form);

        for (const key in overwrites) {
            data.set(key, overwrites[key]);
        }

        for (let row of data) {
            if (!removeEmpty || row[1] !== '') {
                serialized.push(encodeURIComponent(row[0]) + "=" + encodeURIComponent(row[1]));
            }
        }

        return serialized.join('&');
    }
}
