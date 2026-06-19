<!--
  @component Segmented tab-style nav with a sliding active indicator.

  Renders a `tablist` of equal-width tabs. The active tab is highlighted by a
  single pill that slides between tabs with a snappy spring. Supports keyboard
  navigation via the roving-tabindex pattern (←/→/↑/↓, Home, End).

  Bind `value` to the active tab's `key`; `onChange` fires on selection.
-->
<script module lang="ts">
    export interface TabItem {
        /** Stable identifier for the tab, used as `value`. */
        key: string;
        /** Visible label. */
        label: string;
    }
</script>

<script lang="ts">
    import {Spring} from 'svelte/motion';

    interface Props {
        /** The selectable tabs. */
        items: TabItem[];
        /** Key of the active tab, or `null` when none matches. */
        value?: string | null;
        /** Called with the selected tab's key. */
        onChange?: (key: string) => void;
        /** Accessible label for the tablist. */
        'aria-label'?: string;
    }

    let {items, value = $bindable(null), onChange, 'aria-label': ariaLabel}: Props = $props();

    let tabEls = $state<HTMLButtonElement[]>([]);
    const indicator = new Spring({x: 0, w: 0}, {stiffness: 0.55, damping: 0.9});
    let indicatorReady = $state(false);

    $effect(() => {
        const idx = items.findIndex(i => i.key === value);
        const el = idx >= 0 ? tabEls[idx] : null;
        if (!el) {
            indicatorReady = false;
            return;
        }
        const target = {x: el.offsetLeft, w: el.offsetWidth};
        if (!indicatorReady) {
            indicator.set(target, {instant: true});
            indicatorReady = true;
        } else {
            indicator.target = target;
        }
    });

    function select(item: TabItem) {
        value = item.key;
        onChange?.(item.key);
    }

    function handleKeydown(event: KeyboardEvent, index: number) {
        let next = index;
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
        select(items[next]);
        tabEls[next]?.focus();
    }
</script>

<div class="tabs" role="tablist" aria-label={ariaLabel}>
    {#if indicatorReady}
        <span
            class="tabs-indicator"
            aria-hidden="true"
            style="transform: translateX({indicator.current.x}px); width: {indicator.current.w}px;"
        ></span>
    {/if}
    {#each items as item, i (item.key)}
        <button
            type="button"
            role="tab"
            bind:this={tabEls[i]}
            class="tab"
            aria-selected={value === item.key}
            data-active={value === item.key}
            tabindex={value === item.key || (value === null && i === 0) ? 0 : -1}
            onclick={() => select(item)}
            onkeydown={(e) => handleKeydown(e, i)}
        >{item.label}</button>
    {/each}
</div>

<style>
    .tabs {
        position: relative;
        display: flex;
        gap: calc(var(--space-1) / 2);
        padding: calc(var(--space-1) / 2);
        background: var(--color-surface);
        border: none;
        border-radius: var(--corner-full);
    }

    .tabs-indicator {
        position: absolute;
        top: calc(var(--space-1) / 2);
        bottom: calc(var(--space-1) / 2);
        left: 0;
        background: var(--color-surface-raised);
        border-radius: var(--corner-full);
        box-shadow: var(--elevation-1);
        pointer-events: none;
        z-index: 0;
    }

    .tab {
        position: relative;
        z-index: 1;
        flex: 1;
        appearance: none;
        border: none;
        cursor: pointer;
        padding: calc(var(--space-1) * 1.5) var(--space-2);
        border-radius: var(--corner-full);
        background: transparent;
        color: var(--color-text-muted);
        font-family: inherit;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-normal);
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: color var(--duration-fast) var(--easing-default);
    }

    .tab:hover:not([data-active='true']) {
        color: var(--color-text);
    }

    .tab[data-active='true'] {
        color: var(--color-text);
    }

    .tab:focus-visible {
        outline: 1px solid var(--color-focus-ring, var(--color-interactive));
        outline-offset: -1px;
    }

    .tab[data-active='true']:focus-visible {
        outline-offset: 2px;
    }
</style>
