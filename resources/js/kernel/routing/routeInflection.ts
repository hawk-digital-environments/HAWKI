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
 * Currently only used by `createModuleRegistrar()`
 * (`$lib/kernel/modules/moduleRegistrar.ts`), which wraps every module's
 * `routes()` hook in a `registrar.group(getModuleRoutePrefix(...))`.
 */
import {valueToSlug} from '$lib/utils/strings.js';

/**
 * Returns the route prefix of a plugin: an empty string for core plugins (their
 * routes live at the root), `/plugins/<plugin-slug>` for everything else.
 *
 * TODO(docs): This is exported but currently only consumed by
 * {@link getModuleRoutePrefix} — plugin-level `routes()` hooks are handed the
 * *root* registrar by `RoutingExtension.init()`, so routes a plugin registers
 * outside of a module are not prefixed at all. Should plugin routes be wrapped
 * in this prefix as well, or is the unprefixed root access intentional?
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
