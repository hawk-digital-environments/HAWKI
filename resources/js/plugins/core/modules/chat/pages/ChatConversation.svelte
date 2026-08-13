<!--
  @component Page component rendered for the chat module's `/room/:id`
  conversation route (see `ChatModule.ts`, route name `chat.conversation`).
  Placeholder stub — the real conversation view still lives in the legacy,
  non-Svelte-routed part of the app during the ongoing routing migration.

  Usage: never imported directly — resolved lazily by `ChatModule.routes()`
  via `registrar.lazyRoute('/room/:id', ...)` and rendered by the kernel's
  route renderer, which passes the matched route params as `params`.
-->
<script lang="ts">
    import Link from '$lib/components/util/link/Link.svelte';

    interface Props {
        /** Matched route params — `id` of the conversation to display. */
        params: { id: string };
    }

    const {params}: Props = $props();

</script>
<h1>Single chat!</h1>
<p>This is a single conversation with {params.id}</p>
<Link href="@chat.index">Go to chat index</Link>
<hr/>
<Link href="/new">Go back to main page</Link>
