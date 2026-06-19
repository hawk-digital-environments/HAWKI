<script lang="ts">
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import type {Snippet} from 'svelte';
    import {__} from '$lib/utils/translator.js';

    interface Props {
        size?: 'sm' | 'md';
        status: 'online' | 'offline' | 'unknown';
        labelOnline?: string;
        labelOffline?: string;
        labelUnknown?: string;
        tooltipOnline?: Snippet | string;
        tooltipOffline?: Snippet | string;
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
