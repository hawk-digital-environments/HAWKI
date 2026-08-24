<!--
  @component Scrollable menu-row container with proximity hover. Owns the two
  sliding highlights that sit behind the rows — one that follows the current
  selection, one that follows the row nearest the pointer — and the measuring
  needed to keep them there. MenuListItem children register through a dedicated
  context module so the container can track them.

  Rows are laid out as a vertical flex column; the highlight of a row marked
  `inset` is pulled in from the left by `--list-inset`, so nested rows can nest
  their highlight too.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {Attachment} from 'svelte/attachments';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {createProximityHover} from '$lib/utils/proximityHover.svelte.js';
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
    }

    const {children, disabled = false, class: className, ...rest}: Props = $props();

    const hover = createProximityHover({axis: 'y'});

    // Rows register in mount order; indices are handed out monotonically and
    // freed on unmount. Highlights are positioned from measured rects, so the
    // exact index values never need to stay contiguous.
    let nextIndex = 0;
    let itemStates = $state<Record<number, MenuListItemState>>({});
    // The registered row elements, kept alongside the hover's own map so the
    // container can hit-test rows without reaching for a class selector.
    const itemElements = new Map<number, HTMLElement>();

    provideMenuList({
        register(el) {
            const index = nextIndex++;
            itemElements.set(index, el);
            hover.registerItem(index, el);
            return index;
        },
        unregister(index) {
            itemElements.delete(index);
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
    // the highlights snap to the new row positions instead of sliding across
    // from their now-stale coordinates.
    let reflowing = $state(false);
    let settleTimer: ReturnType<typeof setTimeout> | null = null;

    // The active highlight should glide when the *selection itself* moves (e.g.
    // child → parent as a rail collapses) even though that coincides with a
    // reflow. Track index changes so the snap-guard applies only to spurious
    // re-measures of the same row, never to a genuine selection move mid-flight.
    let previousActiveIndex: number | null | undefined;
    let selectionMoved = $state(false);

    function reflow() {
        reflowing = true;
        hover.measureItems();
        if (settleTimer) clearTimeout(settleTimer);
        // Re-enable sliding once the viewport stops changing; clear the
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
        else hover.measureItems();
    });

    // Regions inside the container that are not rows (e.g. a section picker or
    // search trigger) opt out with `data-list-no-hover`: the proximity highlight
    // would otherwise reach for the nearest row while the pointer is somewhere
    // that has nothing to do with it.
    function isExcluded(event: MouseEvent) {
        const target = event.target;
        return target instanceof Element && !!target.closest('[data-list-no-hover]');
    }

    /** Half the row gap — the slack that keeps the gaps between rows "on-row". */
    const GAP_SLACK = 4;

    /** Whether the pointer is over a row, or in a gap directly between two. */
    function nearRow(event: MouseEvent) {
        for (const row of itemElements.values()) {
            const r = row.getBoundingClientRect();
            if (r.height === 0) continue;
            if (event.clientY >= r.top - GAP_SLACK && event.clientY <= r.bottom + GAP_SLACK) {
                return true;
            }
        }
        return false;
    }

    function handleMousemove(event: MouseEvent) {
        // The highlight belongs to a row the pointer is actually on, so it
        // disappears in the empty space around the list instead of reaching for
        // the nearest row. The narrow gaps *between* rows still count as "on a
        // row" — otherwise the highlight would blink off every time the pointer
        // crossed one.
        if (!nearRow(event) || isExcluded(event)) {
            hover.handlers.onmouseleave();
            return;
        }
        hover.handlers.onmousemove(event);
    }

    function handleMouseenter(event: MouseEvent) {
        if (isExcluded(event)) return;
        hover.handlers.onmouseenter();
    }

    const activeRect = $derived(hover.selectedRect);
    const hoverRect = $derived(
        hover.activeIndex !== null ? hover.itemRects[hover.activeIndex] : null
    );
    const hoverOnActive = $derived(hover.activeIndex !== null && hover.activeIndex === activeIndex);
    const activeInset = $derived(activeIndex !== null && (itemStates[activeIndex]?.inset ?? false));
    const hoverInset = $derived(
        hover.activeIndex !== null && (itemStates[hover.activeIndex]?.inset ?? false)
    );
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
    {#if activeRect}
        <span
            class="active-bg"
            class:inset={activeInset}
            class:hidden={disabled}
            class:no-transition={disabled || (reflowing && !selectionMoved)}
            style:top="{activeRect.top}px"
            style:height="{activeRect.height}px"
        ></span>
    {/if}
    {#if hoverRect && !disabled}
        <span
            class="hover-bg"
            class:on-active={hoverOnActive}
            class:inset={hoverInset}
            class:no-transition={reflowing}
            transition:fade={{ duration: 50 }}
            style:top="{hoverRect.top}px"
            style:height="{hoverRect.height}px"
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

    /* Sliding highlight that follows the active selection. */
    .active-bg {
        position: absolute;
        left: 0;
        right: 0;
        z-index: 0;
        pointer-events: none;
        background: var(--color-active-surface);
        border-radius: var(--corner-sm);
        opacity: 1;
        transition:
            top 200ms cubic-bezier(0.34, 1.12, 0.64, 1),
            height 200ms cubic-bezier(0.34, 1.12, 0.64, 1),
            left 200ms cubic-bezier(0.34, 1.12, 0.64, 1),
            background 150ms ease,
            opacity 220ms ease-in-out;
    }

    /* Crossfade the active highlight through a transition rather than letting it
       chase the moving rows. Its position stays pinned while hidden (see
       `no-transition`), so it fades out in place and fades back in already
       sitting on the new active row. */
    .active-bg.hidden {
        opacity: 0;
    }

    /* Single sliding highlight that follows the nearest row. */
    .hover-bg {
        position: absolute;
        left: 0;
        right: 0;
        z-index: 0;
        pointer-events: none;
        background: var(--color-hover);
        border-radius: var(--corner-sm);
        transition:
            top 200ms var(--easing-spring),
            height 200ms var(--easing-spring),
            left 200ms var(--easing-spring);
    }

    /* Hovering the active row darkens its highlight: the active surface is
       translucent in both themes, so a second layer deepens the row. */
    .hover-bg.on-active {
        background: var(--color-active-surface);
    }

    /* During a reflow, snap straight to the re-measured position instead of
       gliding across from stale coordinates. The active highlight keeps its
       opacity transition so it can still crossfade through a transition while
       its position stays pinned. */
    .active-bg.no-transition {
        transition: opacity 220ms ease-in-out;
    }

    .hover-bg.no-transition {
        transition: none;
    }

    /* Nested rows: inset the highlight so it sits under the parent's label. The
       amount is the list's to set — rows only say *that* they are nested. */
    .active-bg.inset,
    .hover-bg.inset {
        left: var(--list-inset, var(--space-4));
    }
</style>
