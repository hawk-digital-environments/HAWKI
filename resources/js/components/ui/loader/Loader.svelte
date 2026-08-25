<!--
  @component Loading indicator that can either replace its children or overlay
  them while preserving the mounted subtree.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** Whether the loading indicator is visible. */
        active: boolean;
        /** Content shown when loading finishes or below an overlay loader. */
        children: Snippet;
        /** Keep children mounted and cover them with the loading surface. */
        overlay?: boolean;
        /** Translated accessible loading text. */
        label: string;
    }

    const {children, active, overlay = false, label, class: className, ...rest}: Props = $props();
</script>

{#if overlay}
    <div {...mergeProps(rest, {class: ['loader-host', className], 'aria-busy': active})}>
        {@render children()}
        {#if active}
            <div class="loader loader--overlay" role="status" aria-label={label}>
                <span class="spinner" aria-hidden="true"></span>
            </div>
        {/if}
    </div>
{:else if active}
    <div {...mergeProps(rest, {class: ['loader', className], role: 'status', 'aria-label': label})}>
        <span class="spinner" aria-hidden="true"></span>
    </div>
{:else}
    {@render children()}
{/if}

<style>
    .loader-host {
        position: relative;
        min-width: 0;
        min-height: 0;
        height: 100%;
    }

    .loader {
        display: grid;
        min-height: 4rem;
        place-items: center;
    }

    .loader--overlay {
        position: absolute;
        inset: 0;
        /* Covers the host's content while it loads. */
        --loader-overlay-z: 1;
        z-index: var(--loader-overlay-z);
        background: color-mix(in oklch, var(--color-bg) 65%, transparent);
        backdrop-filter: blur(1px);
    }

    .spinner {
        width: 1.5rem;
        height: 1.5rem;
        border: 2px solid var(--color-border);
        border-top-color: var(--color-interactive);
        border-radius: 50%;
        animation: spin 700ms linear infinite;
    }

    @keyframes spin {
        to {
            rotate: 1turn;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .spinner {
            animation-duration: 1400ms;
        }
    }
</style>
