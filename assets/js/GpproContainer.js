/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [GPPRO] GpproContainer
 *
 * ServiceContainer for gppro
 */

import GpproConfiguration from './GpproConfiguration';
import GpproTranslation from './GpproTranslation';
import GpproPlugin from './GpproPlugin';

export default class GpproContainer {

    /**
     * Create a new Container with the given configurations and translations.
     *
     * @param {GpproConfiguration} configuration
     * @param {GpproTranslation} translation
     */
    constructor(configuration, translation) {
        if (!(configuration instanceof GpproConfiguration)) {
            throw new Error('Configuration needs to a GpproConfiguration instance');
        }
        this._configuration = configuration;

        if (!(translation instanceof GpproTranslation)) {
            throw new Error('Configuration needs to a GpproTranslation instance');
        }
        this._translation = translation;
        this._plugins = [];
    }

    /**
     * Register a new Plugin.
     *
     * @param {GpproPlugin} plugin
     * @returns {GpproPlugin}
     */
    registerPlugin(plugin) {
        if (!(plugin instanceof GpproPlugin)) {
            throw new Error('Invalid plugin given, needs to be a GpproPlugin instance');
        }

        plugin.setContainer(this);

        this._plugins.push(plugin);

        return plugin;
    }

    /**
     * @param {string} name
     * @returns {GpproPlugin}
     */
    getPlugin(name) {
        for (let plugin of this._plugins) {
            if (plugin.getId() !== null && plugin.getId() === name) {
                return plugin;
            }
        }
        throw new Error('Unknown plugin: ' + name);
    }

    /**
     * @returns {Array<GpproPlugin>}
     */
    getPlugins() {
        return this._plugins;
    }

    /**
     * @returns {GpproTranslation}
     */
    getTranslation() {
        return this._translation;
    }

    /**
     * @returns {GpproConfiguration}
     */
    getConfiguration() {
        return this._configuration;
    }

    /**
     * @returns {GpproUser}
     */
    getUser() {
        return this.getPlugin('user');
    }

}
