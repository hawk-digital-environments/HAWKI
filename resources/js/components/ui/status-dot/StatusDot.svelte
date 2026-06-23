<!--
  @component Colored status indicator dot with an optional inline label and
  hover tooltip. Three statuses map to semantic color tokens: `online` →
  success green, `offline` → error red, `unknown` → warning yellow.
-->
<script lang="ts">
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import type {Snippet} from 'svelte';
    import {__} from '$lib/utils/translator.js';

    interface Props {
        /** Visual size of the dot. `sm` = 8 px, `md` = 10 px. Defaults to `'sm'`. */
        size?: 'sm' | 'md';
        /** Current status; controls the dot color and default tooltip text. */
        status: 'online' | 'offline' | 'unknown';
        /** Optional inline label shown next to the dot when `status` is `'online'`. */
        labelOnline?: string;
        /** Optional inline label shown next to the dot when `status` is `'offline'`. */
        labelOffline?: string;
        /** Optional inline label shown next to the dot when `status` is `'unknown'`. */
        labelUnknown?: string;
        /** Tooltip content for the `'online'` status. Defaults to the translated string. */
        tooltipOnline?: Snippet | string;
        /** Tooltip content for the `'offline'` status. Defaults to the translated string. */
        tooltipOffline?: Snippet | string;
        /** Tooltip content for the `'unknown'` status. Defaults to the translated string. */
        tooltipUnknown?: Snippet | string;
    }

    const {
        size = 'sm',
        status,
        labelOnline,
        labelOffline,
        labelUnknown,
        tooltipOnline = __('ui.statusDot.onlineTooltip'),
        tooltipOffline = __('ui.statusDot.offlineTooltip'),
        tooltipUnknown = __('ui.statusDot.unknownTooltip')
    }: Props = $props();

    const label = $derived.by(() => {
        if (status === 'online') return labelOnline ;
        if (status === 'offline') return labelOffline;
        if (status === 'unknown') return labelUnknown ;
    })
    const showLabel = $derived(!!label);
    const tooltip = $derived.by(() => {
        if (status === 'online') return tooltipOnline;
        if (status === 'offline') return tooltipOffline;
        if (status === 'unknown') return tooltipUnknown;
    });
</script>


<Tooltip tooltip={tooltip} delayDuration={300}>
    {#snippet children({props})}
        <div class="status-dot-wrapper status-dot-wrapper--{status}">
            <span
                class="status-dot status-dot--{size}"
                aria-label={label || __('ui.statusDot.ariaLabel')}
                {...props}
            ></span>
            {#if showLabel}
                <span class="status-dot-label">{label}</span>
            {/if}
        </div>
    {/snippet}
</Tooltip>

<style>
    .status-dot-wrapper {
        --status-dot-bg: var(--color-text-muted);
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .status-dot {
        display: inline-block;
        flex-shrink: 0;
        border-radius: var(--corner-full);
        background-color: var(--status-dot-bg);
    }

    /* ── Sizes ───────────────────────────────────────────────────────── */

    .status-dot--sm {
        width: calc(0.25rem * 2);
        height: calc(0.25rem * 2);
    }

    .status-dot--md {
        width: calc(0.25rem * 2.5);
        height: calc(0.25rem * 2.5);
    }

    /* ── Status variants ─────────────────────────────────────────────── */

    .status-dot-wrapper--online {
        --status-dot-bg: var(--color-success);
    }

    .status-dot-wrapper--unknown {
        --status-dot-bg: var(--color-warning);
    }

    .status-dot-wrapper--offline {
        --status-dot-bg: var(--color-error);
    }

    /* ── Label ───────────────────────────────────────────────────────── */
    .status-dot-label {
        font-size: var(--font-size-xxs);
        color: var(--status-dot-bg);
    }

</style>
