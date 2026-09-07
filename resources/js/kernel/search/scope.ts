/**
 * Scope resolution: turning a session's `allowedScope` and the user's filter
 * choice into one bound the index and the dynamic scheduler can apply.
 *
 * Two rules drive everything here:
 *
 * - **Filters only narrow.** `allowedScope` is what a host permits (a chat
 *   sidebar's bar may be pinned to `core:chat`); the user's own selection can
 *   restrict that further but never widen it. A requested scope that points
 *   outside the allowed one is a *conflict*, not a silent widening.
 * - **A module implies its plugin.** Picking `core:chat` means plugin `core`,
 *   so the two filters combine with AND without the UI having to keep them in
 *   sync.
 *
 * An unknown id or a conflict resolves to invalid, and an invalid scope
 * produces no rows and — importantly — no server requests at all.
 */
import type {SearchScope} from '$lib/kernel/search/types.js';

/** A scope with the module's plugin filled in and `null` meaning "unrestricted". */
export interface ResolvedSearchScope {
    readonly pluginId: string | null;
    readonly moduleId: string | null;
}

export type ScopeResolution =
    | {readonly valid: true; readonly scope: ResolvedSearchScope}
    | {readonly valid: false; readonly reason: string};

/** Which plugins/modules actually have registered search providers right now. */
export interface KnownSearchScope {
    /** Module id → the plugin that owns it. */
    readonly modules: ReadonlyMap<string, string>;
}

export const UNRESTRICTED_SCOPE: ResolvedSearchScope = {pluginId: null, moduleId: null};

/**
 * Combines the host's bound with the user's choice.
 *
 * @param allowed - The session's hard outer bound, if any.
 * @param requested - What the user picked in the filters, if anything.
 * @param known - The registered plugins/modules, used to reject unknown ids.
 */
export function resolveSearchScope(
    allowed: SearchScope | undefined,
    requested: SearchScope | undefined,
    known: KnownSearchScope
): ScopeResolution {
    const allowedModule = normalizeId(allowed?.moduleId);
    const allowedPlugin = normalizeId(allowed?.pluginId);
    const requestedModule = normalizeId(requested?.moduleId);
    const requestedPlugin = normalizeId(requested?.pluginId);

    for (const moduleId of [allowedModule, requestedModule]) {
        if (moduleId !== null && !known.modules.has(moduleId)) {
            return {valid: false, reason: `Unknown search module "${moduleId}".`};
        }
    }
    for (const pluginId of [allowedPlugin, requestedPlugin]) {
        if (pluginId !== null && !hasPlugin(known, pluginId)) {
            return {valid: false, reason: `Unknown search plugin "${pluginId}".`};
        }
    }

    if (allowedModule !== null && requestedModule !== null && allowedModule !== requestedModule) {
        return {valid: false, reason: `Module "${requestedModule}" is outside the allowed module "${allowedModule}".`};
    }
    const moduleId = requestedModule ?? allowedModule;

    // A module carries its plugin, so an explicit plugin filter must agree with it.
    const impliedPlugin = moduleId === null ? null : known.modules.get(moduleId)!;
    for (const pluginId of [allowedPlugin, requestedPlugin]) {
        if (impliedPlugin !== null && pluginId !== null && pluginId !== impliedPlugin) {
            return {valid: false, reason: `Module "${moduleId}" belongs to plugin "${impliedPlugin}", not "${pluginId}".`};
        }
    }

    if (allowedPlugin !== null && requestedPlugin !== null && allowedPlugin !== requestedPlugin) {
        return {valid: false, reason: `Plugin "${requestedPlugin}" is outside the allowed plugin "${allowedPlugin}".`};
    }

    const pluginId = impliedPlugin ?? requestedPlugin ?? allowedPlugin;
    return {valid: true, scope: {pluginId, moduleId}};
}

/** Whether a provider owned by `target` is inside `scope`. */
export function scopeMatches(scope: ResolvedSearchScope, target: {pluginId: string; moduleId: string}): boolean {
    if (scope.moduleId !== null && scope.moduleId !== target.moduleId) {
        return false;
    }
    return !(scope.pluginId !== null && scope.pluginId !== target.pluginId);
}

function hasPlugin(known: KnownSearchScope, pluginId: string): boolean {
    for (const owner of known.modules.values()) {
        if (owner === pluginId) {
            return true;
        }
    }
    return false;
}

function normalizeId(value: string | undefined | null): string | null {
    if (typeof value !== 'string') {
        return null;
    }
    const trimmed = value.trim();
    return trimmed === '' ? null : trimmed;
}
