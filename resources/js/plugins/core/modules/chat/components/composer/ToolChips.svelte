<!--
  @component Row of removable chips showing the currently active AI tools
  (`composerContext.tools.active`). Clicking a chip's remove icon disables that tool via
  `composerContext.tools.disable`; chips for tools no longer compatible with the selected
  model (`!tool.isAvailableFor(composerContext.model.current)`) are styled as `incompatible`.

  Only the chips that fit on a single row (measured against an offscreen mirror row on
  resize/tool-list change) are shown; the rest collapse into a "+N" badge. Renders nothing
  when no tools are active.

  Deliberately not gated on `composerContext.guard.showsAiUiElements`, unlike the model
  picker and the rest of the AI controls: `ToolMenu` and the composer's `/` menu both stay
  usable in a room chat that tags nobody yet, so hiding the chips there would enable tools
  with nothing on screen to show for it.

  ## Usage
  Rendered by `ChatComposer.svelte` in the bottom-left control row, sharing `toolPickerOpen`
  with `ToolMenu` so the overflow badge opens the same picker:
  ```svelte
  let toolPickerOpen = $state(false);
  <ToolMenu bind:open={toolPickerOpen}/>
  <ToolChips onShowMore={() => (toolPickerOpen = true)}/>
  ```
-->
<script lang="ts">
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import ToolIcon from '$plugins/core/modules/chat/components/composer/utils/ToolIcon.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {chipPop} from '$lib/utils/transitions/chipPop';
    import {flip} from 'svelte/animate';
    import {backOut} from 'svelte/easing';
    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    interface Props {
        /** Called when the overflow "+N" badge is clicked. `ChatComposer` uses this to
         *  open `ToolMenu`'s popover so the user can see/manage the hidden tools. */
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
        <ToolIcon tool={tool} size={16}/>
        <span class="tool-chip-label">{tool.displayName}</span>
        <span class="tool-chip-remove" aria-hidden="true"><Cancel01Icon size={16}/></span>
    </button>
{/snippet}

{#if tools.length > 0}
    <!-- Visible row -->
    <div class="tool-chips" bind:this={rowEl} transition:growTransition={{mode: 'horizontal'}}>
        {#each visibleTools as tool (tool)}
            <!-- Wrapper carries the motion so the snippet stays shared with the
                 offscreen measurement row, which must never animate. -->
            <span
                class="tool-chip-slot"
                in:chipPop
                out:chipPop={{direction: 'out'}}
                animate:flip={{duration: 260, easing: backOut}}
            >
                {@render chip(tool)}
            </span>
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

    /* Transform-origin at the start edge keeps the pop growing out of the row rather
       than pushing back into the chip before it. */
    .tool-chip-slot {
        display: inline-flex;
        flex-shrink: 0;
        transform-origin: left center;
    }

    .tool-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--space-1);
        /* Same source as the picker and the assistant tags above; already this value. */
        height: var(--chat-composer-control-height, 2rem);
        max-width: min(16rem, 100%);
        flex-shrink: 0;
        border-radius: var(--corner-full);
        /* Neutral and light: the chip is a container for the tool's own icon swatch,
           which already carries the color. Tinting the pill too made the row shout. */
        background-color: color-mix(in oklab, var(--color-text) 8%, var(--color-surface-raised));
        color: var(--color-text);
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

    /* Held back so it stays subordinate to the tool name, coming forward when the
       pointer is anywhere on the chip — the whole pill is the remove control, but the
       X is the only part that reacts. */
    .tool-chip-remove {
        display: inline-flex;
        line-height: 0;
        opacity: 0.55;
        transition: opacity var(--duration-fast, 150ms);
    }

    .tool-chip:hover .tool-chip-remove,
    .tool-chip:focus-visible .tool-chip-remove {
        opacity: 1;
    }

    @media (prefers-reduced-motion: reduce) {
        .tool-chip-slot {
            transform: none !important;
        }
    }

    .tool-chip-badge {
        font-variant-numeric: tabular-nums;
        font-weight: var(--font-weight-medium, 500);
    }

    .tool-chip.incompatible {
        background-color: color-mix(in oklab, var(--color-error) 12%, var(--color-surface-raised));
        color: var(--color-error);
    }

</style>
