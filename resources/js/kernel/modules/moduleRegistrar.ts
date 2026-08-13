import type {HawkiModule, HawkiModuleWithPlugin} from '$lib/kernel/modules/types.js';
import {getModuleRoutePrefix} from '$lib/kernel/routing/routeInflection.js';
import type {HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';
import type {RouteRegistrar} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

/**
 * Per-plugin registrar factory for the {@link ModuleExtension}.
 *
 * `createModuleRegistrar` is bound to a single plugin and exposes only `add`;
 * `createModuleRegistrarFactory` produces those per-plugin registrars so the
 * `ModuleExtension.init` hook can hand each plugin its own without leaking the
 * shared `modules` Map. Each module is keyed under `${plugin.name}:${module.name}`
 * so collisions throw.
 *
 * The registrar also wraps the module's optional `routes()` callback: the
 * original callback is replaced with one that opens a `registrar.group` under
 * the module's route prefix (computed by {@link getModuleRoutePrefix} —
 * `/[plugins/<plugin>]/<module>`). That means modules only declare their own
 * relative paths and never see the prefix; the routing extension receives them
 * already namespaced.
 */
export function createModuleRegistrar(
    modules: Map<string, HawkiModuleWithPlugin>,
    plugin: HawkiPluginWithMetadata
) {
    function add(module: HawkiModule) {
        if (typeof module.name !== 'string' || module.name.trim() === '') {
            throw new Error(`Module from plugin "${plugin.name}" does not have a valid 'name' property.`);
        }

        const fullModuleName = `${plugin.name}:${module.name}`;
        if (modules.has(fullModuleName)) {
            throw new Error(`Module with name "${fullModuleName}" is already registered.`);
        }

        if (module.routes) {
            // Wrap the routes callback to automatically prefix the module's routes with the plugin and module name.
            const innerRoutes = module.routes;
            module = Object.assign({}, module, {
                routes: async (registrar: RouteRegistrar) => {
                    registrar.group(
                        getModuleRoutePrefix(plugin.name, module.name, plugin.isCorePlugin),
                        innerRoutes,
                        {name: `pluginModule.${plugin.name}.${module.name}`}
                    );
                }
            });
        }

        modules.set(fullModuleName, Object.assign({}, module, {plugin}) as HawkiModuleWithPlugin);
    }

    return {
        add
    };
}

export function createModuleRegistrarFactory(modules: Map<string, HawkiModuleWithPlugin>) {
    return (plugin: HawkiPluginWithMetadata) => createModuleRegistrar(modules, plugin);
}

export type ModuleRegistrar = ReturnType<typeof createModuleRegistrar>;
