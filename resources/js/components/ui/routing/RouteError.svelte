<!--
  @component Fallback shown by `RouterView` when a route cannot be displayed.

  Covers both failure kinds, which arrive through the same props:
  - the route could not be *resolved* (a lazy import failed, a middleware threw)
  - the route rendered and then *crashed*, caught by the view's error boundary

  `reset` retries whichever of the two happened — re-resolving the route, or
  re-rendering the crashed subtree.
-->
<script lang="ts">
    interface Props {
        /** The failure that got us here. Not necessarily an `Error`. */
        error?: unknown;
        /** Retries. Absent only if a consumer renders this component by hand. */
        reset?: () => void;
    }

    const {error, reset}: Props = $props();

    const message = $derived(error instanceof Error ? error.message : error ? String(error) : '');
</script>

<h1>Something went wrong 😵</h1>

{#if message}
    <p class="message">{message}</p>
{/if}

{#if reset}
    <button type="button" onclick={reset}>Try again</button>
{/if}

<style>
    .message {
        color: var(--text-muted, inherit);
    }
</style>
