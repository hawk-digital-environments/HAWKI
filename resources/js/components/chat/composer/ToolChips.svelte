<!--
  @component Row of removable chips showing the currently active AI tools.
  Only the chips that fit on a single row are shown; the rest collapse into a
  "+N" badge that opens the tool picker when clicked.
  Renders nothing when no tools are active.
-->
<script lang="ts">
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import ToolIcon from '$lib/components/chat/composer/utils/ToolIcon.svelte';
    import {__} from '$lib/utils/translator.js';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';

    interface Props {
        /** Called when the overflow "+N" badge is clicked. */
        onShowMore?: () => void;
    }

    let {onShowMore}: Props = $props();

    const composerContext = useComposerContext();

    // Gap between chips, in px (matches the `gap` in .tool-chips below).
    const GAP = 6;

    let rowEl = $state(null as HTMLDivElement | null);
    let measureEl = $state(null as HTMLDivElement | null);

    // Number of chips that fit on a single row; the remainder collapse into the badge.
    let visibleCount = $state(Infinity);

    const tools = $derived(composerContext.tools.active);
    const hiddenCount = $derived(Math.max(0, tools.length - visibleCount));

    function measure() {
        if (!rowEl || !measureEl) return;

        const available = rowEl.clientWidth;
        const chips = Array.from(measureEl.querySelectorAll<HTMLElement>('[data-chip]'));
        const badge = measureEl.querySelector<HTMLElement>('[data-badge]');
        const badgeWidth = badge ? badge.offsetWidth : 0;

        const widths = chips.map(c => c.offsetWidth);
        const total = chips.length;

        // First, see if everything fits without a badge.
        let used = 0;
        let fitAll = true;
        for (let i = 0; i < total; i++) {
            used += widths[i] + (i > 0 ? GAP : 0);
            if (used > available) {
                fitAll = false;
                break;
            }
        }

        if (fitAll) {
            visibleCount = total;
            return;
        }

        // Otherwise reserve room for the badge and count how many chips fit.
        used = 0;
        let count = 0;
        for (let i = 0; i < total; i++) {
            const next = used + widths[i] + (i > 0 ? GAP : 0);
            // Always leave room for the badge (gap + badge width).
            if (next + GAP + badgeWidth > available) {
                break;
            }
            used = next;
            count++;
        }
        visibleCount = count;
    }

    $effect(() => {
        // Re-measure whenever the tool list or container size changes.
        tools;
        if (!rowEl || !measureEl) return;

        measure();

        const ro = new ResizeObserver(() => measure());
        ro.observe(rowEl);
        return () => ro.disconnect();
    });

    const visibleTools = $derived(tools.slice(0, visibleCount));
</script>

{#snippet chip(tool: typeof tools[number], measuring = false)}
    {@const incompatible = !tool.isAvailableFor(composerContext.model.current)}
    <button
        class="tool-chip"
        class:incompatible
        title={tool.displayName}
        tabindex={measuring ? -1 : 0}
        aria-hidden={measuring}
        onclick={() => composerContext.tools.disable(tool)}
        aria-label={__('chat.composer.toolChips.removeToolAriaLabel', {tool: tool.displayName})}
    >
        <ToolIcon tool={tool} size={12}/>
        <span class="tool-chip-label">{tool.displayName}</span>
        <Cancel01Icon size={12}/>
    </button>
{/snippet}

{#if tools.length > 0 && composerContext.guard.showsAiUiElements}
    <!-- Visible row -->
    <div class="tool-chips" bind:this={rowEl} transition:growTransition={{mode: 'horizontal'}}>
        {#each visibleTools as tool (tool)}
            {@render chip(tool)}
        {/each}
        {#if hiddenCount > 0}
            <button
                class="tool-chip tool-chip-badge"
                title={__('chat.composer.toolChips.showMore', {count: String(hiddenCount)})}
                aria-label={__('chat.composer.toolChips.showMore', {count: String(hiddenCount)})}
                onclick={() => onShowMore?.()}
            >
                +{hiddenCount}
            </button>
        {/if}
    </div>

    <!-- Offscreen measurement row: renders every chip plus the badge so we
         can compute how many fit before deciding what to show above. -->
    <div class="tool-chips tool-chips-measure" bind:this={measureEl} aria-hidden="true">
        {#each tools as tool (tool)}
            <span data-chip>{@render chip(tool, true)}</span>
        {/each}
        <span data-badge class="tool-chip tool-chip-badge">+{tools.length}</span>
    </div>
{/if}

<style>
    .tool-chips {
        display: flex;
        flex-wrap: nowrap;
        /* Fill the lane so clientWidth is the available space, not the chip
           content width — otherwise measuring would feed back on itself. */
        width: 100%;
        overflow: hidden;
        gap: calc(0.25rem * 1.5);
    }

    .tool-chips-measure {
        position: absolute;
        visibility: hidden;
        pointer-events: none;
        top: 0;
        left: 0;
        width: 100%;
        flex-wrap: nowrap;
    }

    .tool-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-1);
        height: 2rem;
        max-width: min(16rem, 100%);
        flex-shrink: 0;
        border-radius: var(--corner-full);
        background-color: var(--color-surface);
        color: var(--color-text-muted);
        padding-inline: var(--space-2);
        border: none;
        cursor: pointer;
        font-size: var(--font-size-xxs);
    }

    .tool-chip-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .tool-chip-badge {
        font-variant-numeric: tabular-nums;
        font-weight: var(--font-weight-medium, 500);
    }

    .tool-chip:hover {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    .tool-chip.incompatible {
        background-color: var(--color-error-surface, color-mix(in srgb, var(--color-error) 15%, transparent));
        color: var(--color-error);
    }

    .tool-chip.incompatible:hover {
        background-color: var(--color-error-surface-hover, color-mix(in srgb, var(--color-error) 25%, transparent));
        color: var(--color-error);
    }
</style>
