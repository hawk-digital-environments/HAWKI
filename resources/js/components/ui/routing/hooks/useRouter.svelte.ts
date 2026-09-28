/**
 * Component-side access to the `RouterHandle`s reachable from where a
 * component is mounted (see `router.ts` for what a `RouterHandle` exposes).
 *
 * Every `RouterView` publishes one {@link RouterScope} that names its own
 * router as the current one and delegates every other name to the scope of the
 * `RouterView` above it. A bare `useRouter()` therefore always means *the
 * router rendering me*: the answer is decided by where a component is mounted,
 * never by what some ancestor happened to opt into. Reaching a *different*
 * router is the case that has to be written down — `useRouter('app')`.
 *
 * Delegating rather than copying is what keeps a scope's lifetime tied to its
 * `RouterView`: a scope points at its parent, never at its children, so an
 * unmounted subtree's scopes are unreachable and collected. Nothing has to be
 * de-registered.
 */
import {createHmrSafeContext} from '$lib/utils/hmrSafeContext.js';
import type {RouterHandle} from '$lib/components/ui/routing/logistics/router.js';

/**
 * The routers reachable from one point in the component tree — one link in a
 * chain that has a {@link RouterScope} per enclosing `RouterView`, innermost
 * first.
 *
 * Read it directly (via `useRouterScope`) only when the router to resolve is
 * chosen at runtime and can change while the component is alive, as it can for
 * `<Link router="...">`. Because Svelte context is readable only during
 * initialization, a component in that position has to capture the scope once
 * and resolve names against it later; `useRouter(name)` resolves immediately
 * and is what everything else should call.
 */
export interface RouterScope {
    /** The router of the nearest enclosing `RouterView` — what a bare `useRouter()` returns. */
    readonly current: RouterHandle;
    /**
     * The reachable router named `name`, or `undefined`. The nearest match
     * wins, so a nested router sharing an outer router's name shadows it for
     * its own subtree — the same precedence a bare {@link current} follows.
     */
    get: (name: string) => RouterHandle | undefined;
    /**
     * Every reachable name, nearest first, for error messages. A name a nested
     * router shadows appears once, since only one router answers to it here.
     * Walks the whole chain, so call it on a failure path only.
     */
    names: () => string[];
}

const [getRouterScope, setRouterScope] = createHmrSafeContext<RouterScope>('hawki.router-scope');

/**
 * Publishes the scope covering `handle` and everything already reachable.
 * Called by `RouterView` for its own router.
 *
 * Not part of the public surface: a caller re-pointing `useRouter()` at a
 * router that is not the one rendering the subtree is the ambiguity this
 * context exists to remove.
 *
 * @internal
 */
export function provideRouterScope(handle: RouterHandle): void {
    const parent = readParentScope();
    setRouterScope({
        current: handle,
        get: (name) => (name === handle.name ? handle : parent?.get(name)),
        names: () => (parent ? [handle.name, ...parent.names().filter((n) => n !== handle.name)] : [handle.name])
    });
}

/**
 * Reads the `RouterHandle` of the router named `name`, defaulting to the
 * router of the nearest enclosing `RouterView`.
 *
 * Every member of a `RouterHandle` — `goTo`, `getPath`, `isActive`,
 * `clearData` — is meaningless except relative to one router's route tree and
 * current path, so the enclosing router is the only default that cannot answer
 * a question about the wrong page. A component shared between two routers that
 * navigates by route name is not portable as-is: name the router explicitly if
 * it genuinely means that one, or take the target as a prop.
 *
 * Must be called during component initialization (Svelte context requirement),
 * not inside an event handler or `$effect`.
 *
 * @throws if called outside any `RouterView`, or if `name` names a router that
 *         has no `RouterView` mounted above the caller.
 */
export function useRouter(name?: string): RouterHandle {
    const scope = useRouterScope();
    if (name === undefined) {
        return scope.current;
    }
    const handle = scope.get(name);
    if (!handle) {
        throw new Error(
            `No router named "${name}" is mounted above this component. Reachable from here: ${scope.names().join(', ')}.`
        );
    }
    return handle;
}

/**
 * The {@link RouterScope} of the nearest enclosing `RouterView`, for the
 * runtime-chosen-router case that interface describes. Prefer
 * {@link useRouter}.
 *
 * @throws if called outside any `RouterView`, or outside component
 *         initialization.
 */
export function useRouterScope(): RouterScope {
    const scope = readParentScope();
    if (!scope) {
        throw new Error(
            'No router is available here — this component is not rendered inside a <RouterView>. ' +
            'Note that the router can only be read during component initialization, not from an event handler or an $effect.'
        );
    }
    return scope;
}

/**
 * `createContext`'s getter throws when nothing above set the value. That is an
 * expected branch here — the outermost `RouterView` has no parent scope to
 * extend — so it is turned back into `undefined` rather than propagated.
 */
function readParentScope(): RouterScope | undefined {
    try {
        return getRouterScope();
    } catch {
        return undefined;
    }
}
