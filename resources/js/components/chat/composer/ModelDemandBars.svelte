<!--
  @component Three-bar load indicator showing the current demand on a model.
  More filled bars = higher load. Shows a tooltip on hover.
-->
<script lang="ts">
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import Txt from '$lib/components/ui/Txt.svelte';
    import type {AiModel} from '$lib/schemas/resources/ai-models.schema.js';
    import {__} from '$lib/utils/translator.js';

    interface Props {
        /** The model whose status to show. */
        model: AiModel;
        /** If true, the human-readable demand label will be shown next to the bars. */
        showLabel?: boolean;
    }

    const {model, showLabel}: Props = $props();

    const demand = $derived.by(() => {
        if (model.demand === 'low' || model.demand === 'medium' || model.demand === 'high') {
            return model.demand;
        }
        return 'low';
    });

    const label = $derived.by(() => __('chat.composer.demandBars.' + demand));
    const tooltip = $derived.by(() => __('chat.composer.demandBars.' + demand + 'Tooltip'));

    const filled = $derived.by(() => {
        switch (demand) {
            case 'low':
                return 3;
            case 'medium':
                return 2;
            case 'high':
                return 1;
        }
    });
    const bars = [0, 1, 2] as const;
</script>

<Tooltip tooltip={tooltip}>
    {#snippet children({props})}
        <span class="load-bars" aria-label={__('chat.composer.demandBars.ariaLabel', {label})} {...props}>
            {#each bars as i (i)}
                <span
                    class="load-bar load-bar--h{i + 1} {i < filled ? 'load-bar--active' : 'load-bar--inactive'}"
                ></span>
            {/each}
        </span>
        {#if showLabel}
            <Txt size="xs">
                {label}
            </Txt>
        {/if}
    {/snippet}
</Tooltip>

<style>
    .load-bars {
        display: inline-flex;
        flex-shrink: 0;
        align-items: flex-end;
        gap: calc(0.25rem * 0.625);
    }

    .load-bar {
        width: calc(0.25rem * 0.5);
        border-radius: var(--corner-sm);
    }

    /* ── Heights ─────────────────────────────────────────────────────── */

    .load-bar--h1 {
        height: calc(0.25rem * 1.5);
    }

    .load-bar--h2 {
        height: calc(0.25rem * 2);
    }

    .load-bar--h3 {
        height: calc(0.25rem * 2.5);
    }

    /* ── Fill states ─────────────────────────────────────────────────── */

    .load-bar--active {
        background-color: var(--color-text-muted);
    }

    .load-bar--inactive {
        background-color: var(--color-bg-secondary);
    }
</style>
