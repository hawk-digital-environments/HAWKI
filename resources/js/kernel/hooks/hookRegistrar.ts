import type {HawkiHooks} from '$lib/kernel/extendableTypes.js';
import type {HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';
import type {HawkiHookHandler, HookRegistry} from '$lib/kernel/hooks/types.js';

/** Options accepted when registering a hook handler. */
export interface HookRegistrationOptions {
    /**
     * Execution order within the hook: handlers with a lower `order` run
     * earlier. Defaults to `0`; ties keep plugin registration order (the
     * plugin discovery order, core first — see `PluginExtension`).
     */
    order?: number;
}

/**
 * Per-plugin registrar for hook handlers, handed to a plugin's `hooks()`
 * lifecycle method by `PluginBootstrapper.runHooks()`. Each registration is
 * attributed to the plugin that made it (for error reporting) and ordered
 * by the {@link HookRegistrationOptions.order} option.
 *
 * @example
 * // Inside a plugin's `hooks()` lifecycle method:
 * public hooks(registrar: HookRegistrar): void {
 *     registrar.add('assistantMenuEntries', (entries, ctx) => [
 *         ...entries,
 *         {id: 'my-plugin:action', label: ctx.translate('my-plugin.action'), level: 'dashboard'}
 *     ]);
 * }
 */
export function createHookRegistrar(registry: HookRegistry, plugin: HawkiPluginWithMetadata) {
    /** Registers `handler` under the hook point `name`; throws if the name is blank or the handler is not a function. */
    function add<Name extends keyof HawkiHooks>(
        name: Name,
        handler: HawkiHookHandler<Name>,
        options?: HookRegistrationOptions
    ): void {
        if (typeof name !== 'string' || name.trim() === '') {
            throw new Error(`Hook registration from plugin "${plugin.name}" does not have a valid hook name.`);
        }
        if (typeof handler !== 'function') {
            throw new Error(`Hook "${String(name)}" registered by plugin "${plugin.name}" does not have a valid handler function.`);
        }

        registry.register(name, handler, options?.order ?? 0, plugin);
    }

    return {
        add
    };
}

export type HookRegistrar = ReturnType<typeof createHookRegistrar>;
