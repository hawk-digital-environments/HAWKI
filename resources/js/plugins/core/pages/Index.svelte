<!--
  @component Scratch page exercising `useRouter()` while the SPA router
  (`kernel/routing/`) is scaffolding, not wired into the running app. Not
  reachable through real navigation yet — kept around to manually poke the
  router during development (the button drives `goToRoute`, `onMount` dumps
  the resolved router state via `debug()`). Safe to delete or replace once
  the routing migration produces real root pages.
-->
<script lang="ts">
    import {useRouter} from '@hawk-hhg/hawki-svelte-components';
    import {onMount} from 'svelte';
    import type {RouteProps} from '@hawk-hhg/hawki-svelte-components';

    const {goToRoute, debug} = useRouter();

    const {}: RouteProps = $props();

    onMount(() => {
        debug();
    });
</script>

<button onclick={() => goToRoute('chat.index', {id: '123'})}>Go to Chat</button>
