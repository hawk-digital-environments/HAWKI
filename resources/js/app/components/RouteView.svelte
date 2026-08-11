<script lang="ts">
    import type {Component} from 'svelte';
    import type {RouteContext, RouteParams} from 'universal-router';

    interface Props {
        routeComponent: Component;
        context: RouteContext,
        params: RouteParams,
    }

    const {
        routeComponent: RouteComponent,
        context,
        params
    }: Props = $props();
</script>

<RouteComponent context={context} params={params}/>
