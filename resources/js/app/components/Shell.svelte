<script lang="ts">
    import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
    import {provideApp} from '$lib/app/hooks/useApp.svelte.js';
    import {createToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import RouterView from '$lib/components/ui/routing/RouterView.svelte';
    import Loader from '$lib/components/ui/loader/Loader.svelte';
    import AppLayout from '$lib/app/components/AppLayout.svelte';

    interface Props {
        app: HawkiApp;
    }

    const {app}: Props = $props();

    // svelte-ignore state_referenced_locally
    provideApp(app);
    createToastContext();

</script>

<Loader active={app.isBooting}>
    <AppLayout>
        <RouterView router={(app as any).__router}/>
    </AppLayout>
</Loader>

<style>
    :global(html, body) {
        margin: 0;
        height: 100%;
    }

    :global(#hawki-app) {
        height: 100%;
    }
</style>
