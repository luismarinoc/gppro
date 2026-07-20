/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import GpproAutocomplete from "./GpproAutocomplete";

/**
 * Used for timesheet tagging in toolbar and edit dialogs.
 */
export default class GpproAutocompleteTags extends GpproAutocomplete {

    init()
    {
        this.selector = '[data-form-widget="tags"]';
    }

    loadData(apiUrl, query, callback) {
        /** @type {GpproAPI} API */
        const API = this.getContainer().getPlugin('api');

        API.get(apiUrl, {'name': query}, (data) => {
            let results = [];
            for (let item of data) {
                results.push({text: item.name, value: item.name, color: item['color-safe']});
            }
            callback(results);
        }, () => {
            callback();
        });
    }
}
