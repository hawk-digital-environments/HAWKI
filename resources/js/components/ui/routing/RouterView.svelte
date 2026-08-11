<script lang="ts">

    import type UniversalRouter from 'universal-router';
    import type {RoutingStrategy} from '$lib/components/ui/routing/strategy/types.js';
    import {createTransientRoutingStrategy} from '$lib/components/ui/routing/strategy/transientRoutingStrategy.svelte.js';
    import {createPathRoutingStrategy} from '$lib/components/ui/routing/strategy/pathRoutingStrategy.svelte.js';
    import {createHashRoutingStrategy} from '$lib/components/ui/routing/strategy/hashRoutingStrategy.js';
    import type {Component} from 'svelte';
    import RouteNotFound from '$lib/components/ui/routing/RouteNotFound.svelte';

    interface Props {
        router: UniversalRouter;
        routeResolution?: 'transient' | 'path' | 'hash' | RoutingStrategy;
        notFoundComponent?: Component;
    }

    const {
        router,
        routeResolution = 'transient',
        notFoundComponent = RouteNotFound
    }: Props = $props();

    const NotFoundComponent = notFoundComponent;

    const strategy = $derived.by(() => {
        if (routeResolution === 'transient') {
            return createTransientRoutingStrategy();
        } else if (routeResolution === 'path') {
            return createPathRoutingStrategy();
        } else if (routeResolution === 'hash') {
            return createHashRoutingStrategy();
        } else {
            return routeResolution;
        }
    });

    const currentPath = $derived.by(() => {
        const path = strategy.get();
        if (path === null || path === '') {
            return '/';
        } else {
            return path;
        }
    });

    let RouteComponent = $state<Component | null>(null);
    let routeProps = $state({});
    let notFound = $state(false);

    $effect(() => {
        let isInvalid = false;
        (async () => {
            try {
                const result = await router.resolve(currentPath);
                if (isInvalid) {
                    return;
                }

                if (typeof result === 'function') {
                    RouteComponent = result;
                    routeProps = {};
                    return;
                }

                if (typeof result === 'object' && result !== null) {
                    if ('props' in result && typeof result.props === 'object') {
                        routeProps = result.props;
                    } else {
                        routeProps = {};
                    }
                    if ('component' in result && typeof result.component === 'function') {
                        RouteComponent = result.component;
                        return;
                    } else {
                        throw new Error('Resolved route does not contain a valid component');
                    }
                }

                throw new Error('Resolved route is not a valid component or object with component and props');
            } catch (error) {
                console.error('ROUTER ERROR', error, 'for current path', currentPath);
                notFound = true;
            }
        })();

        return () => {
            isInvalid = true;
        };
    });

    $inspect(currentPath, RouteComponent, router, notFound);
</script>

{#if notFound}
    <NotFoundComponent/>
{:else if RouteComponent !== null}
    <RouteComponent {...routeProps}/>
{/if}
