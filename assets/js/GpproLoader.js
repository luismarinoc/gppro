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

        const kimai = new GpproContainer(
            new GpproConfiguration(configurations),
            new GpproTranslation(translations)
        );

        // GLOBAL HELPER PLUGINS
        kimai.registerPlugin(new GpproUser());
        kimai.registerPlugin(new GpproEscape());
        kimai.registerPlugin(new GpproEvent());
        kimai.registerPlugin(new GpproAPI());
        kimai.registerPlugin(new GpproAlert());
        kimai.registerPlugin(new GpproFetch());
        kimai.registerPlugin(new GpproDateUtils());
        kimai.registerPlugin(new GpproNotification());

        // FORM PLUGINS
        kimai.registerPlugin(new GpproFormSelect('.selectpicker', 'select[data-related-select]'));
        kimai.registerPlugin(new GpproDateRangePicker('input[data-daterangepicker="on"]'));
        kimai.registerPlugin(new GpproDatePicker('input[data-datepicker="on"]'));
        kimai.registerPlugin(new GpproAutocomplete());
        kimai.registerPlugin(new GpproAutocompleteTags());
        kimai.registerPlugin(new GpproTimesheetForm());
        kimai.registerPlugin(new GpproTeamForm());
        kimai.registerPlugin(new GpproCopyDataForm());
        kimai.registerPlugin(new GpproDateNowForm());
        kimai.registerPlugin(new GpproForm());
        kimai.registerPlugin(new GpproHotkeys());

        // SPECIAL FEATURES
        kimai.registerPlugin(new GpproConfirmationLink('confirmation-link'));
        kimai.registerPlugin(new GpproDatatableColumnView('data-column-visibility'));
        kimai.registerPlugin(new GpproDatatable('section.content', 'table.dataTable'));
        kimai.registerPlugin(new GpproToolbar('form.searchform', 'toolbar-action'));
        kimai.registerPlugin(new GpproAlternativeLinks('.alternative-link'));
        kimai.registerPlugin(new GpproAjaxModalForm('.modal-ajax-form', ['td.multiCheckbox', 'td.actions']));
        kimai.registerPlugin(new GpproRemoteModal());
        kimai.registerPlugin(new GpproActiveRecords());
        kimai.registerPlugin(new GpproAPILink('api-link'));
        kimai.registerPlugin(new GpproMultiUpdateTable());
        kimai.registerPlugin(new GpproThemeInitializer());

        // notify all listeners that Kimai plugins can now be registered
        document.dispatchEvent(new CustomEvent('kimai.pluginRegister', {detail: {'kimai': kimai}}));

        // initialize all plugins
        kimai.getPlugins().map(plugin => { plugin.init(); });

        // notify all listeners that Kimai is now ready to be used
        document.dispatchEvent(new CustomEvent('kimai.initialized', {detail: {'kimai': kimai}}));

        this.kimai = kimai;
    }

    getKimai() {
        return this.kimai;
    }

}
