<!--
  @component A strip of tabs that collapse to their icons: only the selected tab
  shows its label, which slides open as the selection arrives. Modelled on Fluid
  Functionalism's "Active Label" tabs.

  One highlight sits behind the selected tab and glides to the next one; a
  fainter one follows the pointer, growing out of the selection and sinking back
  into it when the pointer leaves. Both move on the proximity hover's springs.
  Tabs added or removed later grow in and out instead of shoving their
  neighbours.

  Selection is manual, as in a `tablist` with `activation="manual"`: arrow keys,
  Home and End move focus, Enter or Space selects. A collapsed tab names itself
  in a tooltip.

  When the tabs don't fit, the open label gives way first and fades out where it
  is cut; only once every tab is down to its square does the strip scroll
  sideways, without a scrollbar.

  @example
  ```svelte
  <CollapsibleTabs
      items={[{key: 'chat', label: 'Chat', icon: MessageCircleIcon}, {key: 'code', label: 'Code', icon: CodeIcon}]}
      value={current}
      onChange={key => goTo(key)}
      aria-label="Spaces"
  />
  ```
-->
<script lang="ts">
    import {onDestroy} from 'svelte';
    import type {Attachment} from 'svelte/attachments';
    import CollapsibleTab, {type CollapsibleTabItem} from '$lib/components/ui/tabs/CollapsibleTab.svelte';
    import {createProximityHover} from '$lib/utils/proximityHover.svelte.js';
    import {growTransition} from '$lib/utils/transitions/growTransition.js';
    import {useReducedMotion} from '$lib/utils/transitions/reducedMotion.svelte.js';

    interface Props {
        /** The tabs, in order. */
        items: CollapsibleTabItem[];
        /** Key of the selected tab, or `null` when none is. */
        value?: string | null;
        /** Called with a tab's key when the user selects it. */
        onChange?: (key: string) => void;
        /** Accessible name of the tablist. */
        'aria-label'?: string;
        class?: string;
    }

    const {items, value = null, onChange, 'aria-label': ariaLabel, class: className}: Props = $props();

    const reducedMotion = useReducedMotion();
    const hover = createProximityHover({axis: 'x', hoverFrom: 'selection'});

    // Tabs register under an id that stays with their key, so one arriving or
    // leaving never renumbers the rest.
    const ids = new Map<string, number>();
    let nextId = 0;
    const buttons = new Map<string, HTMLButtonElement>();

    function idOf(key: string): number {
        let id = ids.get(key);
        if (id === undefined) {
            id = nextId++;
            ids.set(key, id);
        }
        return id;
    }

    // A tab's slot changes size as its label slides or as it grows in or out; the
    // tabs beside it move with it, and the highlights follow.
    const slotResize = new ResizeObserver(() => hover.measureItems());
    onDestroy(() => slotResize.disconnect());

    const observeSlot: Attachment<HTMLElement> = (slot) => {
        slotResize.observe(slot);
        return () => slotResize.unobserve(slot);
    };

    function register(key: string): Attachment<HTMLButtonElement> {
        return (button) => {
            const id = idOf(key);
            hover.registerItem(id, button);
            buttons.set(key, button);
            return () => {
                hover.registerItem(id, null);
                if (buttons.get(key) === button) buttons.delete(key);
            };
        };
    }

    const attachStrip: Attachment<HTMLDivElement> = (strip) => {
        hover.setContainer(strip);
        // The strip itself only changes size with the layout (a breakpoint, the
        // sidebar's font bump on small screens), which moves every tab at once: the
        // highlights jump there rather than slide across.
        const stripResize = new ResizeObserver(() => hover.snapToItems());
        stripResize.observe(strip);
        return () => {
            hover.setContainer(null);
            stripResize.disconnect();
        };
    };

    $effect(() => {
        hover.setSelected(value !== null && items.some(item => item.key === value) ? idOf(value) : null);
    });

    // The one tab stop: the selected tab, or the first while none is.
    const focusKey = $derived(items.some(item => item.key === value) ? value : items[0]?.key ?? null);

    function select(key: string) {
        if (key !== value) onChange?.(key);
    }

    function handleKeydown(event: KeyboardEvent, index: number) {
        let next: number;
        switch (event.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                next = (index + 1) % items.length;
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                next = (index - 1 + items.length) % items.length;
                break;
            case 'Home':
                next = 0;
                break;
            case 'End':
                next = items.length - 1;
                break;
            default:
                return;
        }
        event.preventDefault();
        buttons.get(items[next].key)?.focus();
    }

    // The highlight only belongs to a tab the pointer is on, so it lets go in the
    // empty end of the strip instead of reaching for the last tab. The default
    // slack bridges the gaps between tabs, so crossing one doesn't blink it off.
    function handleMousemove(event: MouseEvent) {
        if (hover.isNearItem(event)) hover.handlers.onmousemove(event);
        else hover.handlers.onmouseleave();
    }

    // A slot keeps at least its square, which would stop it growing from nothing;
    // while it grows in or out, the floor is lifted.
    function growSlot(node: Element, direction: 'in' | 'out') {
        if (reducedMotion.current) return {duration: 0};
        const grow = growTransition(node, {mode: 'horizontal', direction});
        return {...grow, css: (t: number) => `${grow.css(t)} min-width: 0;`};
    }

    function slotIn(node: Element) {
        return growSlot(node, 'in');
    }

    function slotOut(node: Element) {
        return growSlot(node, 'out');
    }
</script>

<!-- svelte-ignore a11y_interactive_supports_focus -- the mouse handlers only drive
     the decorative highlight; focus belongs to the tabs, one of which is always a
     tab stop -->
<div
    class={['strip', className]}
    role="tablist"
    aria-label={ariaLabel}
    onmouseenter={hover.handlers.onmouseenter}
    onmousemove={handleMousemove}
    onmouseleave={hover.handlers.onmouseleave}
    {@attach attachStrip}
>
    {#if hover.selectionHighlight}
        {@const rect = hover.selectionHighlight}
        <span
            class="highlight selection"
            class:dimmed={hover.hoverVisible}
            aria-hidden="true"
            style:transform="translate({rect.left}px, {rect.top}px)"
            style:width="{rect.width}px"
            style:height="{rect.height}px"
        ></span>
    {/if}
    {#if hover.hoverHighlight}
        {@const rect = hover.hoverHighlight}
        <span
            class="highlight pointer"
            class:shown={hover.hoverVisible}
            aria-hidden="true"
            style:transform="translate({rect.left}px, {rect.top}px)"
            style:width="{rect.width}px"
            style:height="{rect.height}px"
        ></span>
    {/if}
    {#each items as item, index (item.key)}
        <span class="slot" role="presentation" in:slotIn out:slotOut {@attach observeSlot}>
            <CollapsibleTab
                {item}
                selected={item.key === value}
                active={item.key === value || hover.activeIndex === idOf(item.key)}
                focusable={item.key === focusKey}
                register={register(item.key)}
                onselect={() => select(item.key)}
                onkeydown={event => handleKeydown(event, index)}
            />
        </span>
    {/each}
</div>

<style>
    /* Takes the room it is given rather than hugging its tabs: a strip that
       resized with every sliding label would read each slide as a layout change
       and snap the highlights. */
    .strip {
        position: relative;
        /* One column per tab, at its natural width while they all fit. Short of
           room, the columns share it out evenly from the squares up, so the only
           one that gives way is the one with an open label. */
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: minmax(auto, max-content);
        /* The same small gap as between the sidebar's list rows. */
        column-gap: var(--space-1);
        flex: 1;
        min-width: 0;
        overflow-x: auto;
        overscroll-behavior-x: contain;
        scrollbar-width: none;
    }

    .strip::-webkit-scrollbar {
        display: none;
    }

    /* A collapsed tab is a row-high square and never gets narrower. */
    .slot {
        display: flex;
        min-width: var(--nav-row-h);
    }

    /* Below the tabs, which sit at 1. The selection's fill is the same one the
       sidebar's accent controls use. */
    .highlight {
        position: absolute;
        top: 0;
        left: 0;
        z-index: 0;
        pointer-events: none;
        border-radius: var(--corner-sm);
        background: var(--color-accent-100);
    }

    /* Steps back a little while the pointer is on another tab. Opacity is the only
       thing CSS animates here; position and size come from the springs. */
    .selection {
        transition: opacity 80ms ease;
    }

    .selection.dimmed {
        opacity: 0.8;
    }

    /* A fainter copy of the selection, so the two read as one surface. Out a touch
       quicker than in. */
    .pointer {
        opacity: 0;
        transition: opacity 60ms ease;
    }

    .pointer.shown {
        opacity: 0.4;
        transition-duration: 80ms;
    }

    @media (prefers-reduced-motion: reduce) {
        .highlight {
            transition: none;
        }
    }
</style>
