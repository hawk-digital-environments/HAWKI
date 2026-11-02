<!--
  @component The complete command surface of a search UI: the bits-ui Command
  root with its input and the results list, plus the selection choreography —
  keyboard and pointer gestures, frozen-order re-assertion, the selection
  transaction for clicks and Enter, and IME composition handling — that a
  search bar used to perform against bits-ui internals. This is the only
  component that touches the Command primitives directly; hosts talk to the
  props below. See `commandSelection.svelte.ts` for what the machine decides
  and which bits-ui behaviours it depends on (the version is pinned).

  Announcements, spinners and error rows do not belong here: they are plain
  siblings of this component in the host, not command children.

  @example
  ```svelte
  <CommandSearch
      label={__('ui.search.title')}
      placeholder={__('ui.search.placeholder')}
      bind:value={query}
      resetKey={scopeKey}
      groups={commandGroups}
      selectableValues={selectableValues}
      frozen={frozen}
      onSelect={choose}
      onIntent={() => session?.freezeOrder()}
      onCompositionChange={handleComposition}
      describedBy={hintId}
      iconLeft={Search01Icon}
  />
  ```
-->
<script module lang="ts">
    export type {CommandGroupDefinition} from './CommandResults.svelte';
</script>

<script lang="ts">
    import {Command as CommandPrimitive} from 'bits-ui';
    import {untrack} from 'svelte';
    import CommandResults from './CommandResults.svelte';
    import type {CommandGroupDefinition} from './CommandResults.svelte';
    import {CommandSelection, isNavigationKey} from './commandSelection.svelte.js';
    import {createProximityHover} from '$lib/utils/proximityHover.svelte.js';
    import type {IconComponent} from '$lib/components/ui/icons/index.js';

    interface Props {
        /** Accessible name of the field and its result list. */
        label: string;
        /** Placeholder of the query field. */
        placeholder: string;
        /** The query text. Supports bind:value. */
        value?: string;
        /** Focus the field when mounting. */
        autofocus?: boolean;
        /** The rows, grouped and labelled. */
        groups: CommandGroupDefinition[];
        /** The row values that currently exist and are selectable. */
        selectableValues: ReadonlySet<string>;
        /** Whether the host's row order is frozen; a frozen order keeps the user's highlight. */
        frozen?: boolean;
        /** A change here, like a new scope, resets highlight and selection like typing does. */
        resetKey?: string | number;
        /** Fired with the chosen row's value. The host closes itself first. */
        onSelect: (value: string) => void;
        /** The user showed picking intent: a navigation key, or pointer motion into the rows. */
        onIntent: () => void;
        /** An IME composition opened or closed. */
        onCompositionChange?: (composing: boolean) => void;
        /** id of an element that describes the field. */
        describedBy?: string;
        /** Accessible name of the results list. */
        resultsLabel?: string;
        /** An optional icon before the field, e.g. `Search01Icon`. */
        iconLeft?: IconComponent;
        /** An optional icon at the field's end edge. */
        iconRight?: IconComponent;
        /** Class of the command root element. */
        class?: string;
    }

    let {
        label,
        placeholder,
        value = $bindable(''),
        autofocus = false,
        groups,
        selectableValues,
        frozen = false,
        resetKey = '',
        onSelect,
        onIntent,
        onCompositionChange,
        describedBy,
        resultsLabel,
        iconLeft,
        iconRight,
        class: className = ''
    }: Props = $props();

    const IconLeft = $derived(iconLeft ?? null);
    const IconRight = $derived(iconRight ?? null);

    const selection = new CommandSelection({
        isFrozen: () => frozen,
        selectableValues: () => selectableValues
    });

    let composing = false;

    const hover = createProximityHover();
    let viewport = $state<HTMLElement | null>(null);
    let rows: HTMLElement[] = [];
    let keyboardMoved = false;

    const highlight = $derived(hover.selectedRect);

    // Each changed input — the typed query, or a new resetKey such as the
    // scope — starts the highlight from scratch; a stale highlight would
    // point into a list the user has not seen.
    $effect(() => {
        void value;
        void resetKey;
        untrack(() => {
            selection.reset();
            keyboardMoved = false;
            viewport?.closest('.command-list')?.scrollTo({top: 0});
        });
    });

    // One fill glides to the highlighted row, whichever gesture moved it: it
    // sits on the proximity hover's selected rect, and the hover's nearest row
    // moves the selection below. Queried rather than registered, as the rows
    // belong to CommandResults. Values hold a NUL, which a CSS selector can't
    // match, so rows are compared.
    $effect(() => {
        const current = selection.value;
        void groups;
        hover.setContainer(viewport);
        rows = Array.from(viewport?.querySelectorAll<HTMLElement>('.command-item:not([data-disabled])') ?? []);
        hover.setItems(rows);
        const index = rows.findIndex(candidate => candidate.dataset.value === current);
        hover.setSelected(index === -1 ? null : index);
        if (index !== -1 && keyboardMoved) {
            keyboardMoved = false;
            centerRow(rows[index]);
        }
    });

    // New rows renumber the hover's items, so its nearest row is stale until the
    // pointer really moves again.
    $effect(() => {
        void groups;
        hover.handlers.onmouseleave();
    });

    // The pointer's nearest row becomes the highlighted one. Leaving the list
    // clears the nearest row only, so the highlight stays as Enter's target.
    $effect(() => {
        const index = hover.activeIndex;
        const row = index === null ? undefined : rows[index];
        const rowValue = row?.dataset.value;
        if (rowValue === undefined) return;
        untrack(() => {
            selection.notePointer(rowValue);
            onIntent();
        });
    });

    /** Keeps a row reached by keyboard mid-list, as far as the list's ends allow. */
    function centerRow(row: HTMLElement) {
        const list = viewport?.closest<HTMLElement>('.command-list');
        if (!list || !viewport) return;
        const top = row.offsetTop + viewport.offsetTop - list.offsetTop;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        list.scrollTo({
            top: top + row.offsetHeight / 2 - list.clientHeight / 2,
            behavior: reduceMotion ? 'auto' : 'smooth'
        });
    }

    // Rows changed: drop a selection whose row vanished, re-assert one that
    // survived over the command's first-row bookkeeping.
    $effect(() => {
        void selectableValues;
        untrack(() => selection.syncRows());
    });

    function handleKeydown(event: KeyboardEvent) {
        if (!(event.target instanceof HTMLInputElement) || composing || event.isComposing) {
            return;
        }
        selection.noteKeyboard();
        if (isNavigationKey(event)) {
            keyboardMoved = true;
            // The keyboard takes over; the next real pointer motion takes it
            // back, even onto the same row.
            hover.handlers.onmouseleave();
            onIntent();
        }
        // Enter re-selects the current row and delivers it: the transaction
        // spans from the keypress to the row's onSelect.
        if (event.key === 'Enter') selection.beginTransaction();
    }

    function handleCompositionStart() {
        composing = true;
        onCompositionChange?.(true);
    }

    function handleCompositionEnd() {
        composing = false;
        onCompositionChange?.(false);
    }

    function handlePointerMove(event: MouseEvent) {
        // Stationary pointers are not intent: rows landing under a resting
        // cursor must not steal the highlight.
        if (!event.movementX && !event.movementY) return;
        keyboardMoved = false;
        hover.handlers.onmousemove(event);
    }

    function handleRowPointerDown() {
        // bits-ui sets the value before the row's onSelect runs, so the
        // transaction spans from the press to the delivered selection.
        selection.beginTransaction();
    }

    function handleSelect(rowValue: string) {
        selection.endTransaction();
        onSelect(rowValue);
    }
</script>

<CommandPrimitive.Root
    label={label}
    loop
    shouldFilter={false}
    disablePointerSelection
    bind:value={selection.value}
    onValueChange={next => selection.acceptValueChange(next)}
    onkeydown={handleKeydown}
    class={className}
>
    <div class="command-search-field">
        {#if IconLeft}
            <span class="command-search-icon" aria-hidden="true">
                <IconLeft size={16} />
            </span>
        {/if}
        <CommandPrimitive.Input
            {autofocus}
            class="command-search-input"
            aria-label={label}
            {placeholder}
            aria-describedby={describedBy}
            oncompositionstart={handleCompositionStart}
            oncompositionend={handleCompositionEnd}
            onkeydown={event => {if (composing || event.isComposing) event.stopPropagation();}}
            bind:value
        />
        {#if IconRight}
            <span class="command-search-icon" aria-hidden="true">
                <IconRight size={16} />
            </span>
        {/if}
    </div>
    <CommandResults
        {groups}
        onSelect={handleSelect}
        aria-label={resultsLabel}
        onRowPointerDown={handleRowPointerDown}
        onmousemove={handlePointerMove}
        onmouseenter={hover.handlers.onmouseenter}
        onmouseleave={hover.handlers.onmouseleave}
        bind:viewport
    >
        {#if highlight}
            <span
                class="command-search-highlight"
                style:height="{highlight.height}px"
                style:transform="translateY({highlight.top}px)"
            ></span>
        {/if}
    </CommandResults>
</CommandPrimitive.Root>

<style>
    /* Command renders these elements itself, so they are addressed globally
       under the field's own classes. Size and placement of the field are the
       host's business; the field chrome itself is the same wherever a
       command search is mounted. */
    :global(.command-search-field) {
        display: flex;
        align-items: center;
        flex-shrink: 0;
        gap: var(--space-2_5);
        height: var(--space-12);
        padding-inline: var(--space-4);
    }

    :global(.command-search-input) {
        flex: 1;
        min-width: 0;
        border: none;
        outline: none;
        background: transparent;
        color: var(--color-text);
        font: inherit;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-normal);
        line-height: var(--line-height-normal);
    }

    :global(.command-search-input::placeholder) {
        color: var(--color-text-muted);
    }

    :global(.command-search-icon) {
        display: inline-flex;
        flex-shrink: 0;
        color: var(--color-text-muted);
        transition: color var(--duration-extra-fast) var(--easing-out);
    }

    :global(.command-search-icon path) {
        stroke-width: 1.5;
    }

    /* The field wakes its icon while it holds focus. */
    :global(.command-search-field:focus-within .command-search-icon) {
        color: var(--color-text);
    }

    :global(.command-search-field:focus-within .command-search-icon path) {
        stroke-width: 2;
    }

    /* Paints below the rows, which sit at --command-item-z. */
    :global(.command-search-highlight) {
        position: absolute;
        top: 0;
        inset-inline: 0;
        z-index: 0;
        pointer-events: none;
        background: var(--color-hover);
        border-radius: var(--corner-sm);
        transition: transform var(--duration-extra-fast) var(--easing-spring);
    }

    @media (prefers-reduced-motion: reduce) {
        :global(.command-search-highlight) {
            transition: none;
        }
    }
</style>
