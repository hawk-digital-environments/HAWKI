<!--
  @component Scrollable menu-row container with proximity hover. Owns the two
  sliding highlights that sit behind the rows — one that follows the current
  selection, one that follows the row nearest the pointer — and the measuring
  needed to keep them there. MenuListItem children register through a dedicated
  context module so the container can track them.

  Both highlights move on the proximity-hover helper's springs. Rows are laid out
  as a vertical flex column; the highlight of a row marked `inset` is pulled in
  from the left by `--list-inset`, so nested rows can nest their highlight too, and
  a row marked `destructive` tints the hover highlight in the error hue.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {Attachment} from 'svelte/attachments';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {createProximityHover, type ItemRect} from '$lib/utils/proximityHover.svelte.js';
    import {
        type MenuListItemState,
        provideMenuList
    } from '$lib/components/ui/menu-list/MenuListContext.svelte.js';

    interface Props extends HTMLAttributes<HTMLDivElement> {
        /** The rows (MenuListItem instances, or anything wrapping them). */
        children: Snippet;
        /** Suppress both sliding highlights (active selection + proximity hover),
            e.g. while the list is sliding through a transition. */
        disabled?: boolean;
        /** Keep the proximity highlight on the nearest row while the pointer is anywhere
            between the first and the last row — e.g. over a section label — so it glides
            from section to section instead of disappearing between them. */
        bridgeSections?: boolean;
    }

    const {children, disabled = false, bridgeSections = false, class: className, ...rest}: Props = $props();

    // Rows register in mount order; indices are handed out monotonically and
    // freed on unmount. Highlights are positioned from measured rects, so the
    // exact index values never need to stay contiguous.
    let nextIndex = 0;
    let itemStates = $state<Record<number, MenuListItemState>>({});

    // `--list-inset` may be any CSS length, so a hidden probe resolves it to px.
    let insetProbe = $state<HTMLElement | null>(null);

    // A highlight spans the list's full width, or starts at `--list-inset` on a nested
    // row. Handing that edge to the helper puts it on the springs with everything else,
    // so moving between a nested and a top-level row glides sideways too.
    function placeHighlight(index: number, rect: ItemRect): ItemRect {
        const inset = itemStates[index]?.inset ? (insetProbe?.offsetWidth ?? 0) : 0;
        return {...rect, left: inset};
    }

    const hover = createProximityHover({axis: 'y', adjustRect: placeHighlight});

    provideMenuList({
        register(el) {
            const index = nextIndex++;
            hover.registerItem(index, el);
            return index;
        },
        unregister(index) {
            hover.registerItem(index, null);
            delete itemStates[index];
        },
        setState(index, state) {
            itemStates[index] = state;
        }
    });

    /** Index of the row flagged active, if any. */
    const activeIndex = $derived.by(() => {
        for (const [index, state] of Object.entries(itemStates)) {
            if (state.active) return Number(index);
        }
        return null;
    });

    // While the list is reflowing (breakpoint change, container width animation)
    // the highlights snap to the new row positions instead of gliding across
    // from their now-stale coordinates.
    let reflowing = false;
    let settleTimer: ReturnType<typeof setTimeout> | null = null;

    // The active highlight should glide when the *selection itself* moves even
    // though that coincides with a reflow. Track index changes so the snap applies
    // only to spurious re-measures of the same row, never to a genuine selection
    // move mid-flight.
    let previousActiveIndex: number | null | undefined;
    let selectionMoved = false;

    /** Re-measures the rows. The highlights jump rather than glide while they are
        hidden (`disabled`) or the list merely reflows, so they reappear in place. */
    function remeasure() {
        if (disabled || (reflowing && !selectionMoved)) hover.snapToItems();
        else hover.measureItems();
    }

    function reflow() {
        reflowing = true;
        remeasure();
        if (settleTimer) clearTimeout(settleTimer);
        // Re-enable gliding once the viewport stops changing; clear the
        // selection-move flag so the next reflow snaps as usual.
        settleTimer = setTimeout(() => {
            reflowing = false;
            selectionMoved = false;
        }, 120);
    }

    const attachList: Attachment<HTMLDivElement> = (element) => {
        hover.setContainer(element);
        // The observer fires for the container's own width animation and the
        // initial measure; `resize` covers reflows that change row heights
        // without changing the container box (e.g. a mobile font-size bump).
        const observer = new ResizeObserver(() => reflow());
        observer.observe(element);
        // Rows can also be reordered or swapped without any box changing (a
        // keyed list moving the current row to the top), which neither observer
        // above notices — the highlights would keep sitting on the row that
        // used to be there.
        const mutations = new MutationObserver(() => reflow());
        mutations.observe(element, {childList: true, subtree: true});
        window.addEventListener('resize', reflow);
        return () => {
            hover.setContainer(null);
            observer.disconnect();
            mutations.disconnect();
            window.removeEventListener('resize', reflow);
            if (settleTimer) clearTimeout(settleTimer);
        };
    };

    // One reactive synchronization point keeps the imperative hover helper in
    // step with component inputs. DOM lifecycle/observer work lives in the
    // attachment above instead of being split across several effects.
    $effect(() => {
        // Track registration changes too: the active index can stay the same
        // while the corresponding DOM row is replaced.
        void itemStates;

        if (previousActiveIndex !== undefined && activeIndex !== previousActiveIndex) {
            selectionMoved = true;
        }
        previousActiveIndex = activeIndex;
        hover.setSelected(activeIndex);

        if (disabled) hover.handlers.onmouseleave();
        remeasure();
    });

    // The row the hover highlight last sat on. It keeps that row's tint while it
    // fades out there, instead of switching colour on the way out.
    let hoveredIndex = $state<number | null>(null);
    $effect(() => {
        if (hover.hoverVisible) hoveredIndex = hover.activeIndex;
    });
    const hoverDestructive = $derived(hoveredIndex !== null && (itemStates[hoveredIndex]?.destructive ?? false));

    // Regions inside the container that are not rows (e.g. a section picker or
    // search trigger) opt out with `data-list-no-hover`: the proximity highlight
    // would otherwise reach for the nearest row while the pointer is somewhere
    // that has nothing to do with it.
    function isExcluded(event: MouseEvent) {
        const target = event.target;
        return target instanceof Element && !!target.closest('[data-list-no-hover]');
    }

    function handleMousemove(event: MouseEvent) {
        // The highlight belongs to a row the pointer is actually on, so it
        // disappears in the empty space around the list instead of reaching for
        // the nearest row. The narrow gaps *between* rows still count as "on a
        // row" — otherwise the highlight would blink off every time the pointer
        // crossed one. With `bridgeSections` everything between the first and the
        // last row counts, so section labels don't break the glide — but content
        // above or below the rows (a form control, a header) still clears it.
        const onRows = bridgeSections ? hover.isWithinItems(event) : hover.isNearItem(event);
        if (!onRows || isExcluded(event)) {
            hover.handlers.onmouseleave();
            return;
        }
        hover.handlers.onmousemove(event);
    }

    function handleMouseenter(event: MouseEvent) {
        if (isExcluded(event)) return;
        hover.handlers.onmouseenter();
    }
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -- the mouse handlers
     only drive the decorative proximity highlight; rows stay fully operable
     without them -->
<div
    {...mergeProps(rest, {
        class: ['list', className],
        onmousemove: disabled ? undefined : handleMousemove,
        onmouseenter: disabled ? undefined : handleMouseenter,
        onmouseleave: hover.handlers.onmouseleave
    })}
    {@attach attachList}
>
    <span class="inset-probe" aria-hidden="true" bind:this={insetProbe}></span>
    {#if hover.selectionHighlight}
        {@const rect = hover.selectionHighlight}
        <span
            class="active-bg"
            class:hidden={disabled}
            aria-hidden="true"
            style:top="{rect.top}px"
            style:left="{rect.left}px"
            style:height="{rect.height}px"
        ></span>
    {/if}
    {#if hover.hoverHighlight && !disabled}
        {@const rect = hover.hoverHighlight}
        <span
            class="hover-bg"
            class:shown={hover.hoverVisible}
            class:destructive={hoverDestructive}
            aria-hidden="true"
            style:top="{rect.top}px"
            style:left="{rect.left}px"
            style:height="{rect.height}px"
        ></span>
    {/if}
    {@render children()}
</div>

<style>
    .list {
        position: relative;
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        overflow-y: auto;
        /* Rows may travel on the x-axis during a transition (e.g. a drill-down
           slide); clip that overflow so no horizontal scrollbar flashes
           mid-slide (overflow-y:auto would otherwise resolve overflow-x to auto
           too). */
        overflow-x: hidden;
    }

    /* Resolves `--list-inset` to px for the highlights' left edge. */
    .inset-probe {
        position: absolute;
        top: 0;
        left: 0;
        width: var(--list-inset, var(--space-4));
        height: 0;
        visibility: hidden;
        pointer-events: none;
    }

    /* Both highlights are placed from the helper's springs; only colour and
       opacity transition here. */
    .active-bg,
    .hover-bg {
        position: absolute;
        right: 0;
        /* Highlights paint below the rows (which are positioned at 1). */
        z-index: var(--menu-list-highlight-z, 0);
        pointer-events: none;
        border-radius: var(--corner-sm);
    }

    /* Follows the active selection. */
    .active-bg {
        background: var(--color-active-surface);
        opacity: 1;
        transition:
            background 150ms ease,
            opacity 220ms ease-in-out;
    }

    /* Crossfade the active highlight through a transition rather than letting it
       chase the moving rows. While hidden it snaps (see `remeasure`), so it fades
       out in place and fades back in already sitting on the new active row. */
    .active-bg.hidden {
        opacity: 0;
    }

    /* Follows the row nearest the pointer, and fades out where it lands. */
    .hover-bg {
        background: var(--color-hover);
        opacity: 0;
        transition:
            background-color var(--duration-fast, 150ms) ease,
            opacity var(--duration-fast, 150ms) ease;
    }

    .hover-bg.shown {
        opacity: 1;
    }

    .hover-bg.destructive {
        background: color-mix(in oklch, var(--color-error) 10%, transparent);
    }
</style>
