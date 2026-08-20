<script lang="ts" module>
    export type RadioGroupContext = {
        selected: string | null;
        /** True when the group allows more than one selection at a time. */
        multiple: boolean;
        /** Whether a given value is part of the current selection. */
        isSelected: (v: string) => boolean;
        select: (v: string) => void;
        name: string;
        /** Register an option's button element; returns its stable index. */
        register: (el: HTMLElement) => number;
        /** Release a previously registered index. */
        unregister: (index: number) => void;
        /** Report whether an option is the current selection. */
        setState: (index: number, state: { selected: boolean }) => void;
    };
</script>

<script lang="ts">
    import { setContext } from 'svelte';
    import { createProximityHover } from '$lib/plugins/assistants/utils/proximityHover.svelte.js';

    let {
        value = $bindable<string | string[] | null>(null),
        onchange = null,
        multiple = false,
        name = 'radio-group',
        children
    } = $props<{
        /** Single value, or an array of values when `multiple` is set. */
        value?: string | string[] | null;
        /** Fires with the toggled value and whether it is now selected. */
        onchange?: ((v: string, selected: boolean) => void) | null;
        multiple?: boolean;
        name?: string;
        children: import('svelte').Snippet;
    }>();

    // Same proximity-hover behaviour as the app-layout sidebar: a single
    // highlight glides to the nearest option, and a second one tracks the
    // current selection.
    const hover = createProximityHover({ axis: 'y' });
    let listEl = $state<HTMLElement | null>(null);
    $effect(() => hover.setContainer(listEl));

    let nextIndex = 0;
    let itemStates = $state<Record<number, { selected: boolean }>>({});

    const ctx: RadioGroupContext = {
        get selected() { return Array.isArray(value) ? (value[0] ?? null) : value; },
        get multiple() { return multiple; },
        isSelected(v: string) {
            return Array.isArray(value) ? value.includes(v) : value === v;
        },
        select(v: string) {
            if (multiple) {
                const current = Array.isArray(value) ? value : value ? [value] : [];
                const nowSelected = !current.includes(v);
                value = nowSelected ? [...current, v] : current.filter((x) => x !== v);
                onchange?.(v, nowSelected);
            } else {
                value = v;
                onchange?.(v, true);
            }
        },
        get name() { return name; },
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
    };

    setContext<RadioGroupContext>('radioGroup', ctx);

    /** Indices of all selected options. */
    const selectedIndices = $derived.by(() => {
        const indices: number[] = [];
        for (const [index, state] of Object.entries(itemStates)) {
            if (state.selected) indices.push(Number(index));
        }
        return indices;
    });

    /** Index of the selected option, whose highlight slides into place. */
    const selectedIndex = $derived(selectedIndices[0] ?? null);

    $effect(() => {
        itemStates;
        // Only the single-select highlight glides; multiselect paints a static
        // background per selected row instead.
        hover.setSelected(multiple ? null : selectedIndex);
    });

    const activeRect = $derived(hover.selectedRect);

    /** Static per-row backgrounds for multiselect mode. */
    const activeRects = $derived(
        multiple ? selectedIndices.map((i) => hover.itemRects[i]).filter(Boolean) : []
    );
    const hoverRect = $derived(
        hover.activeIndex !== null ? hover.itemRects[hover.activeIndex] : null
    );
    const hoverOnActive = $derived(
        hover.activeIndex !== null && hover.activeIndex === selectedIndex
    );
</script>

<div
    class="radio-switch"
    role="radiogroup"
    bind:this={listEl}
    onmousemove={hover.handlers.onmousemove}
    onmouseenter={hover.handlers.onmouseenter}
    onmouseleave={hover.handlers.onmouseleave}
>
    {#if multiple}
        {#each activeRects as rect}
            <span
                class="active-bg static"
                style:top="{rect.top}px"
                style:height="{rect.height}px"
            ></span>
        {/each}
    {:else if activeRect}
        <span
            class="active-bg"
            style:top="{activeRect.top}px"
            style:height="{activeRect.height}px"
        ></span>
    {/if}
    {#if hoverRect}
        <span
            class="hover-bg"
            class:on-active={hoverOnActive}
            style:top="{hoverRect.top}px"
            style:height="{hoverRect.height}px"
        ></span>
    {/if}
    {@render children()}
</div>


<style>
    .radio-switch{
        position: relative;
        display: flex;
        flex-direction: column;
        gap: .5rem;
        justify-content: start;
    }

    /* Sliding highlight that follows the selected option. */
    .active-bg{
        position: absolute;
        left: 0;
        right: 0;
        z-index: 0;
        pointer-events: none;
        background: var(--color-accent-100);
        border-radius: var(--corner-md);
        transition:
            top 200ms cubic-bezier(0.34, 1.12, 0.64, 1),
            height 200ms cubic-bezier(0.34, 1.12, 0.64, 1),
            background 150ms ease;
    }

    /* Multiselect paints one background per selected row, so it should not
       glide between positions — only fade in/out. */
    .active-bg.static{
        transition: background 150ms ease;
    }

    /* Sliding highlight that follows the nearest option (proximity hover). */
    .hover-bg{
        position: absolute;
        left: 0;
        right: 0;
        z-index: 0;
        pointer-events: none;
        background: var(--color-hover);
        border-radius: var(--corner-md);
        transition:
            top 200ms var(--easing-spring),
            height 200ms var(--easing-spring);
    }

    /* Hovering the selected option deepens its highlight. */
    .hover-bg.on-active{
        background: var(--color-accent-100);
    }
</style>
