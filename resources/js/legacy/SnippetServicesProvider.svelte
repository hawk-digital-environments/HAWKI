<!--
  @component Internal wrapper mounted by `svelteSnippetLoader.ts` instead of
  the target snippet component directly.

  WHY: every `<svelte-snippet>` is its own independent Svelte root (see the
  loader's module doc), so a context set in `Shell.svelte` never reaches it.
  `@hawk-hhg/hawki-svelte-components`' components (e.g. `BorderBeam`,
  `Markdown`, `Link`, `UrlPreviewTooltip`) read their host services
  (colour scheme, link services, ...) via context with a working default when
  none is provided — this wrapper calls `provideComponentServices(app)`
  during its own component initialization so those defaults are overridden
  with the real app services inside every legacy snippet root, exactly like
  `Shell.svelte` does for the SPA root. See `provideComponentServices.ts` for
  the single place all such services are wired.
-->
<script lang="ts">
    import type {Component} from 'svelte';
    import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
    import {provideComponentServices} from '$lib/app/provideComponentServices.js';

    interface Props {
        /** The fully-booted `HawkiApp` instance. */
        app: HawkiApp;
        /** The snippet component to render, resolved by `app.snippets.get(type)`. */
        component: Component<Record<string, any>>;
        /** Props to forward to `component` (includes the reserved `root` prop). */
        componentProps: Record<string, unknown>;
    }

    const {app, component: SnippetComponent, componentProps}: Props = $props();

    // svelte-ignore state_referenced_locally
    provideComponentServices(app);
</script>

<SnippetComponent {...componentProps} />
