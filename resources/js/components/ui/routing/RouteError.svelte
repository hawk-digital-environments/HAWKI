<!--
  @component Fallback shown by `RouterView` when a route cannot be displayed.

  Covers both failure kinds, which arrive through the same props:
  - the route could not be *resolved* (a lazy import failed, a middleware threw)
  - the route rendered and then *crashed*, caught by the view's error boundary

  `reset` retries whichever of the two happened — re-resolving the route, or
  re-rendering the crashed subtree.
-->
<script lang="ts">
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {RouteHttpError} from './logistics/signals.js';

    interface Props {
        /** The failure that got us here. Not necessarily an `Error`. */
        error?: unknown;
        /** Retries. Absent only if a consumer renders this component by hand. */
        reset?: () => void;
    }

    const {error, reset}: Props = $props();
    const {__} = useTranslator();

    const message = $derived(error instanceof Error ? error.message : error ? String(error) : '');
</script>

<h1>{__(error instanceof RouteHttpError && error.status === 403 ? 'admin.forbidden_title' : 'ui.routing.errorTitle')}</h1>

{#if message}
    <p class="message">{error instanceof RouteHttpError && error.status === 403 ? __('admin.forbidden') : message}</p>
{/if}

{#if reset}
    <button type="button" onclick={reset}>{__('ui.routing.retry')}</button>
{/if}

<style>
    .message {
        color: var(--color-text-muted, inherit);
    }
</style>
