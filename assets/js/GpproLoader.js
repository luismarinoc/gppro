/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] GpproLoader: bootstrap the application and all plugins
 */

import { Settings } from 'luxon';
import GpproTranslation from "./GpproTranslation";
import GpproConfiguration from "./GpproConfiguration";
import GpproContainer from "./GpproContainer";
import GpproDatatableColumnView from './plugins/GpproDatatableColumnView.js';
import GpproThemeInitializer from "./plugins/GpproThemeInitializer";
import GpproDateRangePicker from "./forms/GpproDateRangePicker";
import GpproDatatable from "./plugins/GpproDatatable";
import GpproToolbar from "./plugins/GpproToolbar";
import GpproAPI from "./plugins/GpproAPI";
import GpproAlternativeLinks from "./plugins/GpproAlternativeLinks";
import GpproAjaxModalForm from "./plugins/GpproAjaxModalForm";
import GpproActiveRecords from "./plugins/GpproActiveRecords";
import GpproEvent from "./plugins/GpproEvent";
import GpproAPILink from "./plugins/GpproAPILink";
import GpproAlert from "./plugins/GpproAlert";
import GpproAutocomplete from "./forms/GpproAutocomplete";
import GpproFormSelect from "./forms/GpproFormSelect";
import GpproForm from "./plugins/GpproForm";
import GpproDatePicker from "./forms/GpproDatePicker";
import GpproConfirmationLink from "./plugins/GpproConfirmationLink";
import GpproMultiUpdateTable from "./plugins/GpproMultiUpdateTable";
import GpproDateUtils from "./plugins/GpproDateUtils";
import GpproEscape from "./plugins/GpproEscape";
import GpproFetch from "./plugins/GpproFetch";
import GpproTimesheetForm from "./forms/GpproTimesheetForm";
import GpproTeamForm from "./forms/GpproTeamForm";
import GpproCopyDataForm from "./forms/GpproCopyDataForm";
import GpproDateNowForm from "./forms/GpproDateNowForm";
import GpproNotification from "./plugins/GpproNotification";
import GpproHotkeys from "./plugins/GpproHotkeys";
import GpproRemoteModal from "./plugins/GpproRemoteModal";
import GpproUser from "./plugins/GpproUser";
import GpproAutocompleteTags from "./forms/GpproAutocompleteTags";

export default class GpproLoader {

    constructor(configurations, translations) {
        // set the current locale for all javascript components
        Settings.defaultLocale = configurations['locale'].replace('_', '-').toLowerCase();
        Settings.defaultZone = configurations['timezone'];

        const gppro = new GpproContainer(
            new GpproConfiguration(configurations),
            new GpproTranslation(translations)
        );

        // GLOBAL HELPER PLUGINS
        gppro.registerPlugin(new GpproUser());
        gppro.registerPlugin(new GpproEscape());
        gppro.registerPlugin(new GpproEvent());
        gppro.registerPlugin(new GpproAPI());
        gppro.registerPlugin(new GpproAlert());
        gppro.registerPlugin(new GpproFetch());
        gppro.registerPlugin(new GpproDateUtils());
        gppro.registerPlugin(new GpproNotification());

        // FORM PLUGINS
        gppro.registerPlugin(new GpproFormSelect('.selectpicker', 'select[data-related-select]'));
        gppro.registerPlugin(new GpproDateRangePicker('input[data-daterangepicker="on"]'));
        gppro.registerPlugin(new GpproDatePicker('input[data-datepicker="on"]'));
        gppro.registerPlugin(new GpproAutocomplete());
        gppro.registerPlugin(new GpproAutocompleteTags());
        gppro.registerPlugin(new GpproTimesheetForm());
        gppro.registerPlugin(new GpproTeamForm());
        gppro.registerPlugin(new GpproCopyDataForm());
        gppro.registerPlugin(new GpproDateNowForm());
        gppro.registerPlugin(new GpproForm());
        gppro.registerPlugin(new GpproHotkeys());

        // SPECIAL FEATURES
        gppro.registerPlugin(new GpproConfirmationLink('confirmation-link'));
        gppro.registerPlugin(new GpproDatatableColumnView('data-column-visibility'));
        gppro.registerPlugin(new GpproDatatable('section.content', 'table.dataTable'));
        gppro.registerPlugin(new GpproToolbar('form.searchform', 'toolbar-action'));
        gppro.registerPlugin(new GpproAlternativeLinks('.alternative-link'));
        gppro.registerPlugin(new GpproAjaxModalForm('.modal-ajax-form', ['td.multiCheckbox', 'td.actions']));
        gppro.registerPlugin(new GpproRemoteModal());
        gppro.registerPlugin(new GpproActiveRecords());
        gppro.registerPlugin(new GpproAPILink('api-link'));
        gppro.registerPlugin(new GpproMultiUpdateTable());
        gppro.registerPlugin(new GpproThemeInitializer());

        // notify all listeners that Kimai plugins can now be registered
        document.dispatchEvent(new CustomEvent('gppro.pluginRegister', {detail: {'gppro': gppro}}));

        // initialize all plugins
        gppro.getPlugins().map(plugin => { plugin.init(); });

        // notify all listeners that Kimai is now ready to be used
        document.dispatchEvent(new CustomEvent('gppro.initialized', {detail: {'gppro': gppro}}));

        this.gppro = gppro;
    }

    getGppro() {
        return this.gppro;
    }

}
