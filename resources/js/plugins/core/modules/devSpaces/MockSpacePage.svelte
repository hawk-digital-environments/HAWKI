<!--
  @component Placeholder page behind the dev-only mock spaces (see `mockSpaces.ts`).
  Its text is a fixture, never shown to users, so it skips the translator.
-->
<script lang="ts">
    import Page from '$lib/components/ui/page/Page.svelte';
    import type {RouteProps} from '$lib/components/ui/routing/index.js';

    const {}: RouteProps = $props();
</script>

<Page title="Mock space"/>
