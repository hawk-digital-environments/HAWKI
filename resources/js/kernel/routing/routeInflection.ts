import {valueToSlug} from '$lib/utils/strings.js';

export function getPluginRoutePrefix(pluginName: string, isCorePlugin: boolean): string {
    if (isCorePlugin) {
        return '';
    }
    const slug = valueToSlug(pluginName);
    return `/plugins/${slug}`;
}

export function getModuleRoutePrefix(pluginName: string, moduleName: string, isCorePlugin: boolean): string {
    const pluginPrefix = getPluginRoutePrefix(pluginName, isCorePlugin);
    const moduleSlug = valueToSlug(moduleName);
    return `${pluginPrefix}/${moduleSlug}`;
}
