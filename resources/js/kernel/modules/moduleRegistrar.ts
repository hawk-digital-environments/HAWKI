import type {HawkiModule, HawkiModuleWithPlugin} from '$lib/kernel/modules/types.js';
import {getModuleRouteGroupName, getModuleRoutePrefix} from '$lib/kernel/routing/routeInflection.js';
import type {HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';
import type {RouteRegistrar} from '$lib/components/ui/routing/index.js';

/**
 * Copies a module, adding `props` on top of it.
 *
 * Modules are normally class instances, so `title()`, `icon()`, `description()`,
 * `routes()` and `sidebar()` live on the prototype. The copy therefore has to be
 * created *with that prototype in place* — a plain `Object.assign({}, module)`
 * copies own enumerable properties only, which keeps `name` but silently drops
 * every method the module declares.
 */
function extendModule(module: HawkiModule, props: Record<string, unknown>): HawkiModule {
    return Object.assign(Object.create(Object.getPrototypeOf(module)), module, props);
}

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

        const instance = module;

        if (module.routes) {
            // Wrap the routes callback to automatically prefix the module's routes with the plugin and module name.
            // Bound to the original instance, since it is handed on as a bare callback.
            const innerRoutes = module.routes.bind(instance);
            module = extendModule(module, {
                routes: async (registrar: RouteRegistrar) => {
                    registrar.group(
                        getModuleRoutePrefix(plugin.name, instance.name, plugin.isCorePlugin),
                        innerRoutes,
                        {name: getModuleRouteGroupName(plugin.name, instance.name)}
                    );
                }
            });
        }

        modules.set(fullModuleName, extendModule(module, {plugin}) as HawkiModuleWithPlugin);
    }

    return {
        add
    };
}

export function createModuleRegistrarFactory(modules: Map<string, HawkiModuleWithPlugin>) {
    return (plugin: HawkiPluginWithMetadata) => createModuleRegistrar(modules, plugin);
}

export type ModuleRegistrar = ReturnType<typeof createModuleRegistrar>;
