<!--
  @component Root Svelte island mounted into `#hawki-app` by `ShellExtension`.
  Provides the booted `HawkiApp` via Svelte context (`provideApp`) and sets up
  the shared toast context, then renders a loading indicator while
  `app.isBooting` is true and `RouterView` once bootstrap has passed
  `finalization`.

  `RouterView` needs the full `Router` instance, but the public app surface
  only exposes the narrower `RouterHandle` as `app.router`. `__router` is
  `RoutingExtension`'s `@internal` escape hatch for exactly this case — the
  `any` cast below reaches for it deliberately, since it is not part of
  `HawkiAppExtensions`.
-->
<script lang="ts">
    import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
    import {provideApp} from '$lib/app/hooks/useApp.svelte.js';
    import {createToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import RouterView from '$lib/components/ui/routing/RouterView.svelte';
    import Loader from '$lib/components/ui/loader/Loader.svelte';
    import AppLayout from '$lib/app/components/AppLayout.svelte';

    interface Props {
        /** The fully-assembled `HawkiApp` instance, passed in by `ShellExtension.mount()`. */
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
