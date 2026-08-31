import type {default as UniversalRouter, RouteContext, RouteError} from 'universal-router';
import type {RouteComponent, RouteLayout, RouteMeta, RouteResultBody} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import type {CreateRouterOptions, Router} from '$lib/components/ui/routing/logistics/router.js';
import type {RoutingStrategy} from '$lib/components/ui/routing/strategy/types.js';
import type {RouterNodeTree} from '$lib/components/ui/routing/logistics/nodeTree.js';
import type {RouteDataCache} from '$lib/components/ui/routing/logistics/dataCache.js';
import {normalizePath} from '$lib/components/ui/routing/logistics/normalizePath.js';

export class RouterState {
    public currentState: Router['state'] = $state('loading');
    public currentError: Error | RouteError | null = $state.raw(null);
    public resolvePath: string | null = $state(null);
    public currentPath: string | null = $state(null);
    public currentContext: RouteContext<RouteResultBody> | null = $state.raw(null);
    public currentComponent: RouteComponent | null = $state(null);
    public currentLayouts: RouteLayout[] = $state.raw([]);
    public currentMeta: RouteMeta | null = $state.raw(null);
    public currentNodeData: ReadonlyArray<Record<string, unknown>> = $state.raw([]);
    public currentNodeParams: ReadonlyArray<unknown> = $state.raw([]);
    /**
     * The run currently allowed to publish — see {@link startRun}. Never read
     * directly: a run holds the signal it was handed and asks *that*, so it
     * still recognises itself as superseded after a newer run has replaced
     * this field.
     */
    private currentRun: AbortController | null = null;
    /**
     * Disposers registered (via `context.onCleanup`) by the run that is
     * currently on screen. Held separately from the in-flight run's own list:
     * a new run collects into its own array and only takes this slot once it
     * publishes — see {@link commitCleanups}.
     */
    private activeCleanups: Array<() => void> = [];

    /**
     * Wires the routing strategy into the router for the lifetime of the
     * calling component — reached through `Router.bind()`, which `RouterView`
     * calls once during its init.
     *
     * Must run during a component's initialisation: `$effect` registers against
     * whatever component is currently initialising, which is also what ties the
     * strategy's teardown to that component being destroyed.
     *
     * `runResolve` is passed in rather than held on the state because resolving
     * is `router.ts`'s job — this class only owns the reactive fields the
     * resolution publishes into.
     */
    public bind(runResolve: (path: string) => void): void {
        $effect(() => this.strategy.bind?.(this.name, this.basePath) ?? (() => void 0));

        $effect(() => {
            const newPath = normalizePath(this.strategy.get());
            if (newPath === this.resolvePath) {
                return;
            }
            runResolve(newPath);
        });

        // Teardown of the whole router, not of one resolution: whatever the
        // rendered route registered has to go with the `RouterView` that was
        // showing it. Aborting first makes an in-flight run take its own
        // rollback path (see `routeResolver.ts`'s `finally`), so its cleanups
        // are disposed too rather than outliving the component.
        $effect(() => () => {
            this.abortCurrentRun();
            this.disposeActiveCleanups();
        });
    }

    public constructor(
        public readonly name: string,
        public readonly options: CreateRouterOptions | undefined,
        public readonly strategy: RoutingStrategy,
        public readonly basePath: string,
        public readonly nodeTree: RouterNodeTree,
        public readonly innerRouter: UniversalRouter,
        public readonly dataCache: RouteDataCache
    ) {
    }

    /**
     * Supersedes whatever run was in flight and returns the signal the new one
     * must re-check after every `await`. Aborting is the *whole* cancellation
     * mechanism — there is no second generation counter — so it means both
     * "stop your in-flight `restApi` calls" (the signal is handed to loaders
     * via `ctx.signal`) and "you no longer own the router state, publish
     * nothing". A run that sees `aborted` returns without touching `state`.
     */
    public startRun(): AbortSignal {
        this.currentRun?.abort();
        this.currentRun = new AbortController();
        return this.currentRun.signal;
    }

    /**
     * Cancels the in-flight run without starting a replacement — for `goTo()`,
     * which has to invalidate the current resolution before the routing
     * strategy has even told `bind()`'s `$effect` there is a new path to
     * resolve.
     *
     * Returns whether a run was actually cancelled. `goTo()` needs to know: it
     * has just left the router with no owner, so if the strategy then reports
     * that its path did not change either, nothing would restart the
     * resolution and the router would sit in `loading` forever.
     */
    public abortCurrentRun(): boolean {
        if (!this.currentRun) {
            return false;
        }
        this.currentRun.abort();
        this.currentRun = null;
        return true;
    }

    /**
     * Retires the run owning `signal` once it has published, so
     * {@link abortCurrentRun} stops reporting it as in flight. Matches on
     * identity rather than clearing unconditionally: a run that was superseded
     * — or that redirected into a nested one — no longer owns `currentRun` and
     * must not retire whichever run took over from it.
     */
    public finishRun(signal: AbortSignal): void {
        if (this.currentRun?.signal === signal) {
            this.currentRun = null;
        }
    }

    /**
     * Hands the on-screen slot to the run that just published: disposes the
     * previous route's cleanups and adopts `cleanups` as the active set.
     *
     * This — not the run's `AbortSignal` — is what "the user navigated away"
     * means for a disposer. A run that publishes successfully is retired by
     * {@link finishRun} *without* its controller ever being aborted, so a
     * completed run's signal never fires and cannot be used to time teardown.
     */
    public commitCleanups(cleanups: Array<() => void>): void {
        const previous = this.activeCleanups;
        this.activeCleanups = cleanups;
        disposeAll(previous);
    }

    /** Disposes the on-screen run's cleanups, leaving nothing active. */
    public disposeActiveCleanups(): void {
        const active = this.activeCleanups;
        this.activeCleanups = [];
        disposeAll(active);
    }
}

/**
 * Runs every disposer last-registered-first, so teardown mirrors set-up across
 * a nested middleware stack. Each is isolated: one throwing disposer must not
 * strand the ones after it, or a single bad listener would leak every other
 * subscription the route registered.
 */
export function disposeAll(cleanups: Array<() => void>): void {
    for (let i = cleanups.length - 1; i >= 0; i--) {
        try {
            cleanups[i]();
        } catch (error) {
            console.error('Error while disposing a route cleanup', error);
        }
    }
}
