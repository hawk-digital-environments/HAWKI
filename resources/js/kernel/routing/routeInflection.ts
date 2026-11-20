/**
 * Helpers that derive the URL prefixes under which plugin- and module-provided
 * routes live, so route paths stay collision-free without every plugin having
 * to hardcode its own namespace.
 *
 * The rule is: core plugins own the root of the URL space, everything shipped
 * by a third-party plugin is pushed below `/plugins/<plugin-slug>`. Names are
 * slugified with {@link valueToSlug}, so a display name like `My Plugin` yields
 * `my-plugin`.
 *
 * Used by `createModuleRegistrar()` (`$lib/kernel/modules/moduleRegistrar.ts`),
 * which wraps every module's `routes()` hook in a
 * `registrar.group(getModuleRoutePrefix(...))`, and by
 * `PluginBootstrapper.runRoutes()` (`$lib/kernel/plugins/PluginBootstrapper.ts`),
 * which does the same for a plugin's own `routes()` hook via
 * `getPluginRoutePrefix(...)` directly.
 */
import {valueToSlug} from '$lib/utils/strings.js';

/**
 * Returns the route prefix of a plugin: an empty string for core plugins (their
 * routes live at the root), `/plugins/<plugin-slug>` for everything else.
 *
 * @example
 * getPluginRoutePrefix('core', true);        // ''
 * getPluginRoutePrefix('My Plugin', false);  // '/plugins/my-plugin'
 */
export function getPluginRoutePrefix(pluginName: string, isCorePlugin: boolean, pluginNameInRoutes?: boolean): string {
    const slug = valueToSlug(pluginName);
    if (isCorePlugin) {
        if(pluginNameInRoutes){
            return `/${slug}`
        }
        return '';
    }
    return `/plugins/${slug}`;
}

/**
 * Returns the route prefix of a single module: its owning plugin's prefix
 * (see {@link getPluginRoutePrefix}) followed by the slugified module name.
 *
 * @example
 * getModuleRoutePrefix('core', 'chat', true);         // '/chat'
 * getModuleRoutePrefix('My Plugin', 'Chat', false);   // '/plugins/my-plugin/chat'
 */
export function getModuleRoutePrefix(pluginName: string, moduleName: string, isCorePlugin: boolean, pluginNameInRoutes?: boolean): string {
    const pluginPrefix = getPluginRoutePrefix(pluginName, isCorePlugin, pluginNameInRoutes);
    const moduleSlug = valueToSlug(moduleName);
    return `${pluginPrefix}/${moduleSlug}`;
}

/**
 * Returns the route-group name a module's routes are registered under (see
 * `createModuleRegistrar`). Pass it to `router.isRouteActive()` to check
 * whether the module is the one currently shown.
 *
 * @example
 * getModuleRouteGroupName('core', 'chat'); // 'pluginModule.core.chat'
 */
export function getModuleRouteGroupName(pluginName: string, moduleName: string): string {
    return `pluginModule.${pluginName}.${moduleName}`;
}
