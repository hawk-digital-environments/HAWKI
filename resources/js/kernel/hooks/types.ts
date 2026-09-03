import type {HawkiHooks} from '$lib/kernel/extendableTypes.js';
import type {HawkiPluginWithMetadata} from '$lib/kernel/plugins/types.js';

/**
 * A single hook implementation: receives the value produced by the handlers
 * that ran before it (or the initial value `apply` was called with) plus the
 * hook's context, and returns the value to hand to the next handler — a pure
 * `(value, ctx) => value` filter. Handlers may also remove or reorder
 * entries other plugins contributed; they get the final say of their own
 * position in the chain via the registration's `order`.
 */
export type HawkiHookHandler<Name extends keyof HawkiHooks> = (
    value: HawkiHooks[Name]['value'],
    ctx: HawkiHooks[Name]['ctx']
) => HawkiHooks[Name]['value'];

/**
 * The registry contract a {@link HookExtension} fulfills; handed to
 * `createHookRegistrar` so the per-plugin registrars can feed it without
 * depending on the extension class itself (mirrors how the other kernel
 * registrar/extension pairs split their types).
 */
export interface HookRegistry {
    register<Name extends keyof HawkiHooks>(
        name: Name,
        handler: HawkiHookHandler<Name>,
        order: number,
        plugin: HawkiPluginWithMetadata
    ): void;
}
