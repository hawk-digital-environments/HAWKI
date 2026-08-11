<script lang="ts">
    import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
    import Loader from '$lib/app/components/shell/Loader.svelte';
    import {provideApp} from '$lib/app/hooks/useApp.svelte.js';
    import {createToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import RouterView from '$lib/components/ui/routing/RouterView.svelte';
    import {createPathRoutingStrategy} from '$lib/components/ui/routing/strategy/pathRoutingStrategy.svelte.js';

    interface Props {
        app: HawkiApp;
    }

    const {app}: Props = $props();

    // svelte-ignore state_referenced_locally
    provideApp(app);
    createToastContext();

    // @todo temporary base route until the SPA pattern is fully implemented
    const strategy = createPathRoutingStrategy({basePath: '/new'});
</script>

<Loader active={app.isBooting}>
    <RouterView router={app.router} routeResolution={strategy}/>
</Loader>
