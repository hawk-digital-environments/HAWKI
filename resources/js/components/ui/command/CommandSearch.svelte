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
      hint={escKeycap}
  />
  ```
-->
<script module lang="ts">
    export type {CommandGroupDefinition} from './CommandResults.svelte';
</script>

<script lang="ts">
    import {Command as CommandPrimitive} from 'bits-ui';
    import {untrack, type Snippet} from 'svelte';
    import CommandResults from './CommandResults.svelte';
    import type {CommandGroupDefinition} from './CommandResults.svelte';
    import {CommandSelection, isNavigationKey} from './commandSelection.svelte.js';
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
        /** Trailing non-icon content, e.g. an Escape keycap. */
        hint?: Snippet;
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
        hint,
        class: className = ''
    }: Props = $props();

    const IconLeft = $derived(iconLeft ?? null);
    const IconRight = $derived(iconRight ?? null);

    const selection = new CommandSelection({
        isFrozen: () => frozen,
        selectableValues: () => selectableValues
    });

    let composing = false;

    // Each changed input — the typed query, or a new resetKey such as the
    // scope — starts the highlight from scratch; a stale highlight would
    // point into a list the user has not seen.
    $effect(() => {
        void value;
        void resetKey;
        untrack(() => selection.reset());
    });

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
        if (isNavigationKey(event)) onIntent();
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

    function handleRowPointerMove(rowValue: string, event: PointerEvent) {
        // Stationary pointers are not intent: rows landing under a resting
        // cursor must not steal the highlight.
        if (!event.movementX && !event.movementY) return;
        selection.notePointer(rowValue);
        onIntent();
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
                <IconLeft size={18} strokeWidth={2} />
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
        {#if hint}
            <span class="command-search-hint">{@render hint()}</span>
        {/if}
        {#if IconRight}
            <span class="command-search-icon" aria-hidden="true">
                <IconRight size={18} strokeWidth={2} />
            </span>
        {/if}
    </div>
    <CommandResults
        {groups}
        onSelect={handleSelect}
        aria-label={resultsLabel}
        onRowPointerMove={handleRowPointerMove}
        onRowPointerDown={handleRowPointerDown}
    />
</CommandPrimitive.Root>

<style>
    /* Command renders these elements itself, so they are addressed globally
       under the field's own classes. Size and placement of the field are the
       host's business; the field chrome itself is the same wherever a
       command search is mounted. */
    :global(.command-search-field) {
        display: flex;
        align-items: center;
        gap: var(--space-2_5);
        padding: var(--space-3) var(--space-4);
        border-bottom: var(--border);
    }

    :global(.command-search-input) {
        flex: 1;
        min-width: 0;
        border: none;
        outline-offset: var(--space-1);
        background: transparent;
        color: var(--color-text);
        font: inherit;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
    }

    :global(.command-search-input::placeholder) {
        color: var(--color-text-muted);
    }

    :global(.command-search-icon) {
        display: inline-flex;
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    :global(.command-search-hint) {
        display: inline-flex;
        flex-shrink: 0;
    }
</style>
