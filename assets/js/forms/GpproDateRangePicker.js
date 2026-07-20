/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [GPPRO] GpproDateRangePicker: activate the (daterange picker) compound field in toolbar
 */

import GpproDatePicker from "./GpproDatePicker";

export default class GpproDateRangePicker extends GpproDatePicker {

    prepareOptions(options)
    {
        return {...options, ...{
            plugins: ['mobilefriendly'],
            singleMode: false,
            autoRefresh: true,
        }};
    }

}
