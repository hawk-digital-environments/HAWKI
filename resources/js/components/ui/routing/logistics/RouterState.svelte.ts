import type {default as UniversalRouter, RouteContext, RouteError} from 'universal-router';
import type {RouteComponent, RouteLayout, RouteMeta, RouteResultBody} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';
import type {CreateRouterOptions, Router} from '$lib/components/ui/routing/logistics/router.svelte.js';
import type {RoutingStrategy} from '$lib/components/ui/routing/strategy/types.js';
import type {RouterNodeTree} from '$lib/components/ui/routing/logistics/nodeTree.js';
import type {RouteDataCache} from '$lib/components/ui/routing/logistics/dataCache.js';

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
}
