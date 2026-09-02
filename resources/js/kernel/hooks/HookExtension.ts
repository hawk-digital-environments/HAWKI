import type {HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {HawkiHooks} from '$lib/kernel/extendableTypes.js';
import type {HawkiHookHandler, HookRegistry} from '$lib/kernel/hooks/types.js';
import type {HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';
import {createHookRegistrar} from '$lib/kernel/hooks/hookRegistrar.js';

/**
 * Declaration merging that exposes this extension on the app object as
 * `app.hooks` (see {@link HawkiAppExtension} / `createApp()` in
 * `kernel/HawkiApp.ts`).
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        hooks: WithoutAppExtensionInternals<HookExtension>;
    }
}

/** A handler as stored in the registry, with its ordering metadata. */
interface StoredRegistration {
    handler: (value: any, ctx: any) => any;
    order: number;
    seq: number;
    pluginName: string;
}

/**
 * App extension that owns the central registry of hook handlers and applies
 * them — HAWKI's extension mechanism for data a surface owns but other
 * plugins may shape.
 *
 * A hook point is declared by its surface owner via the {@link HawkiHooks}
 * declaration-merging interface; plugins register handlers from their
 * `hooks()` lifecycle method through a per-plugin {@link HookRegistrar}
 * (built by `hookRegistrar.ts`); the owning surface applies the hook with
 * {@link apply} at whatever point it needs the filtered value (for reactive
 * UI surfaces, inside a `$derived` — see `useSidebarHooks.svelte.ts`).
 *
 * Handlers are pure filters: each receives the previous handler's result
 * and returns the next value, so a plugin may append, remove, or reorder
 * entries other plugins contributed.
 */
export class HookExtension implements HawkiAppExtension, HookRegistry {
    private readonly handlers = new Map<keyof HawkiHooks, StoredRegistration[]>();
    private seq = 0;

    /** Hook names that have at least one registered handler. */
    public get names(): string[] {
        return Array.from(this.handlers.keys()) as string[];
    }

    /** Stores a handler with its ordering metadata. Called only through the per-plugin registrars (see `hookRegistrar.ts`). */
    public register<Name extends keyof HawkiHooks>(
        name: Name,
        handler: HawkiHookHandler<Name>,
        order: number,
        plugin: HawkiPluginWithMetadata
    ): void {
        const registrations = this.handlers.get(name) ?? [];
        registrations.push({
            handler: handler as (value: any, ctx: any) => any,
            order,
            seq: this.seq++,
            pluginName: plugin.name
        });
        // Explicit `seq` tiebreak keeps the sort deterministic and stable
        // regardless of the platform's sort stability.
        registrations.sort((a, b) => a.order - b.order || a.seq - b.seq);
        this.handlers.set(name, registrations);
    }

    /**
     * Applies every handler registered for `name`, in `order` (ties keep
     * registration order), threading `value` through: each handler receives
     * the previous handler's result and returns the next value. Handler
     * failures are isolated — a throwing handler is logged and skipped, and
     * the value produced so far survives — so one broken plugin cannot
     * blank the surface the hook feeds. If no handler is registered, the
     * initial `value` is returned unchanged.
     */
    public apply<Name extends keyof HawkiHooks>(
        name: Name,
        value: HawkiHooks[Name]['value'],
        ctx: HawkiHooks[Name]['ctx']
    ): HawkiHooks[Name]['value'] {
        let result = value;
        for (const registration of this.handlers.get(name) ?? []) {
            try {
                result = registration.handler(result, ctx);
            } catch (error) {
                console.error(`Error while applying hook "${String(name)}" handler from plugin "${registration.pluginName}":`, error);
            }
        }
        return result;
    }

    /** Collects every plugin's hook handlers via the plugin bootstrapper's `runHooks` stage. */
    public async init(app: UnfinishedHawkiApp) {
        await app.plugins!.bootstrapper.runHooks(
            plugin => createHookRegistrar(this, plugin)
        );
    }

    /** Exposes this extension as `app.hooks`. */
    public provideProperties(): Record<string, any> {
        return {
            hooks: this
        };
    }
}
