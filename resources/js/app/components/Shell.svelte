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
    import {provideComponentServices} from '$lib/app/provideComponentServices.js';
    import {createToastContext, Loader, RouterView} from '@hawk-hhg/hawki-svelte-components';

    interface Props {
        /** The fully-assembled `HawkiApp` instance, passed in by `ShellExtension.mount()`. */
        app: HawkiApp;
    }

    const {app}: Props = $props();

    // svelte-ignore state_referenced_locally
    provideApp(app);
    createToastContext();
    // svelte-ignore state_referenced_locally
    provideComponentServices(app);

</script>

<Loader active={app.isBooting}>
    <RouterView router={(app as any).__router}/>
</Loader>
