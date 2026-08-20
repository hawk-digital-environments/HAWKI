<!--
  @component Renders whatever a `Router` (see `logistics/router.ts`)
  currently resolves to: a loading state, the matched page nested in its
  layout stack, the 404 fallback, or an error fallback. Publishes the
  router's `RouterHandle` into the `RouterScope` its subtree reads through
  `useRouter()`, as the router a bare `useRouter()` means — so a nested
  `RouterView` transparently redirects its subtree to its own router while
  leaving the outer routers reachable by name.

  One `RouterView` is expected per `Router` instance — it calls
  `router.bind()` on init, which wires the router up to its routing strategy
  (path/hash/transient) for the lifetime of this component.
-->
<script lang="ts">
    import type {Component} from 'svelte';
    import RouteNotFound from '$lib/components/ui/routing/RouteNotFound.svelte';
    import RouteError from '$lib/components/ui/routing/RouteError.svelte';
    import Loader from '$lib/components/ui/loader/Loader.svelte';
    import type {Router} from '$lib/components/ui/routing/logistics/router.js';
    import {provideRouterScope} from '$lib/components/ui/routing/hooks/useRouter.svelte.js';

    interface Props {
        /** The router instance to render (from `createRouter`/`createRouterFromRegistrar`, or `app.router`). */
        router: Router;
        /** Translated accessible label for the navigation loading indicator. */
        loadingLabel: string;
        /** Rendered when no route matches the current path. Defaults to `RouteNotFound`. */
        notFoundComponent?: Component;
        /** Rendered when resolution fails or a rendered route crashes. Defaults to `RouteError`. See `RouteError.svelte` for the `error`/`reset` contract. */
        errorComponent?: Component<{ error: unknown; reset: () => void }>;
    }

    const {
        router,
        loadingLabel,
        notFoundComponent = RouteNotFound,
        errorComponent = RouteError
    }: Props = $props();

    const NotFoundComponent = $derived(notFoundComponent);
    const ErrorComponent = $derived(errorComponent);

    const routerState = $derived(router.state);
    const hasRoutingError = $derived(routerState === 'error');
    const isLoading = $derived(routerState === 'loading');
    const isNotFound = $derived(routerState === 'notFound');
    const RouteComponent = $derived(router.component);
    const layouts = $derived(router.layouts);
    const route = $derived(router.route);
    const nodeData = $derived(router.nodeData);
    const nodeParams = $derived(router.nodeParams);
    // One object for the whole chain, not one per node — see `Router.meta`.
    const meta = $derived(router.meta ?? {});

    // Makes this router the one a bare `useRouter()` below resolves to, and
    // every router above it still reachable by name. A nested `RouterView`
    // shadows the outer one for its own subtree, which is the whole point — a
    // layout shared by both asks the router that is actually rendering it.
    // svelte-ignore state_referenced_locally
    provideRouterScope(router.handle);

    // svelte-ignore state_referenced_locally
    router.bind();

    function handleError(error: unknown) {
        console.error('Error while rendering route component', error);
    }
</script>

<!--
  Nests the layout stack (outermost first) around the page. Recursing over an
  index instead of shrinking the array keeps each level's component expression
  pointing at the same reference across navigations, so a layout shared by two
  routes stays mounted while only the page inside it swaps out.
-->
{#snippet layoutStack(index: number)}
    {#if index < layouts.length}
        {@const Layout = layouts[index]}
        <Layout data={nodeData[index] ?? {}} params={nodeParams[index] ?? {}} {meta} {route}>
            {@render layoutStack(index + 1)}
        </Layout>
    {:else}
        {@render page()}
    {/if}
{/snippet}

<!--
  The two ways a route can fail need different recoveries but present the same
  contract to `ErrorComponent`:
  - resolution failed — nothing rendered, so retrying means resolving again.
    Stays inside the layout stack, like the 404 does.
  - the route rendered and crashed — caught by the boundary below, which has
    already torn the subtree (layouts included) down; `reset` re-renders it.
-->
{#snippet errorPage(error: unknown, reset: () => void)}
    <ErrorComponent {error} {reset}/>
{/snippet}

{#snippet page()}
    {#if hasRoutingError}
        {@render errorPage(router.error, () => void router.handle.reload())}
    {:else if isNotFound}
        <NotFoundComponent/>
    {:else if RouteComponent}
        <!-- The page is always the last entry of the render chain `[...layouts, page]` — see `Router.nodeData`'s doc comment. -->
        <RouteComponent data={nodeData[layouts.length] ?? {}} params={nodeParams[layouts.length] ?? {}} {meta} {route}/>
    {/if}
{/snippet}

<svelte:boundary onerror={handleError}>
    <Loader active={isLoading} overlay label={loadingLabel}>
        {@render layoutStack(0)}
    </Loader>
    {#snippet failed(error, reset)}
        {@render errorPage(error, reset)}
    {/snippet}
</svelte:boundary>
