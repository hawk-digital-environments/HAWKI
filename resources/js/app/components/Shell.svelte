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

    interface Props {
        /** The fully-assembled `HawkiApp` instance, passed in by `ShellExtension.mount()`. */
        app: HawkiApp;
        /** Server-rendered label used before frontend translations have loaded. */
        bootstrapLoadingLabel: string;
    }

    const {app, bootstrapLoadingLabel}: Props = $props();

    // svelte-ignore state_referenced_locally
    provideApp(app);
    createToastContext();
    const loadingLabel = $derived.by(() => {
        if (app.isBooting) return bootstrapLoadingLabel;
        return app.localization.translator.translate('ui.loading');
    });

</script>

<Loader active={app.isBooting} label={loadingLabel}>
    <RouterView router={(app as any).__router} loadingLabel={loadingLabel}/>
</Loader>

<style>
    /* `#hawki-app` is created by `index.blade.php`; give it, and its ancestors, the
       height chain a full-height page (or the boot loader) needs to size against the
       viewport. The `:has()` guard keeps this off the legacy Blade pages, which share
       the same `html`/`body` but never contain the mount node. */
    :global(html:has(#hawki-app)),
    :global(body:has(#hawki-app)) {
        margin: 0;
        height: 100%;
    }

    :global(#hawki-app) {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
</style>
