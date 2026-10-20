<!--
  @component A single toggleable tool row inside `ToolMenuList`'s `DropdownMenuCheckboxItem`
  list: the tool's icon on a swatch, its name, and its description truncated to one line —
  the same row shape the composer's `/` menu shows via `ToolRow`.

  The whole row toggles the tool (checkbox semantics, wired to `entry.onToggle`); a
  separate info chevron on the right — revealed on the row being pointed at or focused, as in
  `ModelMenuListItem` — opens `ToolMenuDetail` for that tool instead, stopping propagation so
  it never also toggles. Like the model list, only exceptions get a status dot: a tool the
  current model doesn't support (yellow) and one that is offline (red), whose row is also
  faded as disabled.

  Registers itself with `ToolMenuFocusContext` under `entry.tool.name` so `ToolMenuGroupHeader`
  info triggers interspersed in the list can be reached via ArrowUp/ArrowDown/Tab (bits-ui's
  built-in roving tabindex only knows about menu items, not those popover triggers).
  ArrowRight opens the detail view directly, mirroring the info button.

  ## Usage
  Rendered by `ToolMenuList`, once per entry in each of the three sections (capabilities,
  function tools, per-MCP-server groups):
  ```svelte
  {#each entries.functionTools as entry (entry.tool.name)}
      <ToolMenuListItem entry={entry} onOpenDetail={onOpenDetail}/>
  {/each}
  ```
-->
<script lang="ts">
    import DropdownMenuCheckboxItem from '$lib/components/ui/dropdown-menu/DropdownMenuCheckboxItem.svelte';
    import type {ToolMenuEntry} from '$plugins/core/modules/chat/components/composer/ToolMenu.svelte';
    import ToolIcon from '$plugins/core/modules/chat/components/composer/utils/ToolIcon.svelte';
    import {useToolMenuFocusContext} from '$plugins/core/modules/chat/components/composer/contexts/ToolMenuFocusContext.svelte.js';
    import StatusDotForTool from '$plugins/core/modules/chat/components/composer/StatusDotForTool.svelte';
    import ArrowRight01Icon from '$lib/components/ui/icons/iconset/ArrowRight01Icon.svelte';
    import MenuPinButton from '$plugins/core/modules/chat/components/composer/MenuPinButton.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    interface Props {
        /** The tool row to render, including its toggle callback and active/available/disabled flags. */
        entry: ToolMenuEntry;
        /** Called with `entry` when the user clicks the info button or presses ArrowRight.
         *  `ToolMenu` uses this to open `ToolMenuDetail` for that tool. */
        onOpenDetail?: (entry: ToolMenuEntry) => void;
    }

    const {entry, onOpenDetail}: Props = $props();
    const focusContext = useToolMenuFocusContext();

    let rowEl = $state<HTMLDivElement | null>(null);

    // The list only surfaces exceptions; the detail view always shows the status.
    const showStatus = $derived(entry.disabled || !entry.available);

    const infoTooltip = $derived.by(() => {
        if (entry.disabled) return __('chat.composer.toolMenu.infoOffline');
        if (!entry.available) return __('chat.composer.toolMenu.infoUnsupported');
        return __('chat.composer.toolMenu.infoDefault');
    });

    $effect(() => {
        if (!rowEl) return;
        return focusContext.register(entry.tool.name, rowEl, 'tool');
    });

    function openDetail(event: MouseEvent) {
        event.preventDefault();
        event.stopPropagation();
        onOpenDetail?.(entry);
    }

    // The picker mixes tool rows with MCP-group info triggers. When the next/prev
    // focusable in DOM order is a group-info button, the bits-ui menu's roving
    // tabindex won't reach it, so steer focus manually.
    function onRowKeydown(event: KeyboardEvent) {
        if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            const direction: 1 | -1 = event.key === 'ArrowDown' ? 1 : -1;
            const neighbor = focusContext.getAdjacent(entry.tool.name, direction);
            if (neighbor?.kind === 'group-info') {
                event.preventDefault();
                event.stopPropagation();
                neighbor.element.focus();
            }
            return;
        }
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            event.stopPropagation();
            onOpenDetail?.(entry);
            return;
        }
        // Tab walks the picker without wrapping: past the last (or before the
        // first) entry the event reaches the bits-ui menu, which closes it and
        // moves focus on to the next control after the trigger.
        if (event.key === 'Tab') {
            if (focusContext.focusAdjacent(entry.tool.name, event.shiftKey ? -1 : 1, false)) {
                event.preventDefault();
                event.stopPropagation();
            }
        }
    }
</script>
<!--
  The whole row toggles the tool (checkbox). The info icon is the only target
  that opens the detail view and stops propagation so it never toggles.
-->
<DropdownMenuCheckboxItem
    bind:ref={rowEl}
    checked={entry.active}
    closeOnSelect={false}
    onCheckedChange={entry.onToggle}
    disabled={entry.disabled}
    onkeydown={onRowKeydown}
    data-tool-name={entry.tool.name}
    aria-keyshortcuts="ArrowRight"
    class="tool-menu-item">
    {#snippet children(checked)}
        <span class="tool-item-main">
            <ToolIcon tool={entry.tool} swatch checked={checked}/>
            <span class="tool-item-text">
                <span class="tool-item-label">{entry.tool.displayName}</span>
                <span class="tool-item-description">{entry.tool.description}</span>
            </span>
            {#if showStatus}
                <StatusDotForTool
                    tool={entry.tool}
                    supported={entry.available}
                    focusable={false}/>
            {/if}
            <MenuPinButton kind="tool" id={entry.tool.name}/>
            <button
                type="button"
                class="tool-item-info"
                aria-label={infoTooltip}
                tabindex={-1}
                onpointerdown={(e) => e.stopPropagation()}
                onpointerup={(e) => e.stopPropagation()}
                onkeydown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.stopPropagation();
                    }
                }}
                onclick={openDetail}>
                <ArrowRight01Icon size={16}/>
            </button>

        </span>
    {/snippet}
</DropdownMenuCheckboxItem>

<style>
    :global(.tool-menu-item) {
        gap: var(--space-2, calc(0.25rem * 2));
    }

    /* The whole row toggles the tool, so signal it as clickable. */
    :global(.dropdown-checkbox-item.tool-menu-item) {
        cursor: pointer;
        /* Check now lives on the icon swatch, so drop the reserved right padding. */
        padding-right: var(--space-2, calc(0.25rem * 2));
        /* Matches the vertical inset, so the swatch sits evenly in the row's corner. */
        padding-left: var(--space-1_5);
    }

    /* Concentric with the row's highlight: its corner radius minus the inset, so the gap
       around the swatch stays even into the corner too. */
    :global(.dropdown-checkbox-item.tool-menu-item .tool-icon-swatch) {
        border-radius: calc(var(--corner-sm) - var(--space-1_5));
    }

    /* Two lines at the shared tight leading run a fraction past the 32px swatch and stretch
       the row; a 16px line each keeps the text exactly swatch-tall, so the swatch's inset
       above and below matches the one on the left. */
    :global(.dropdown-checkbox-item.tool-menu-item :is(.tool-item-label, .tool-item-description)) {
        line-height: calc(0.25rem * 4);
    }

    /* An offline tool fades as a whole row, as an offline model does in `ModelMenuListItem`,
       rather than only greying its label. It still takes the pointer, so hovering reveals
       its info chevron and the user can read why. The doubled class outranks the checkbox
       item's own `opacity: 1`. */
    :global(.dropdown-checkbox-item.tool-menu-item.tool-menu-item[data-disabled]) {
        cursor: not-allowed;
        opacity: 0.5;
    }

    :global(.dropdown-checkbox-item.tool-menu-item[data-disabled] .tool-item-label) {
        color: var(--color-text);
    }

    /* Built-in absolute check is replaced by the one laid over the icon swatch. */
    :global(.dropdown-checkbox-item.tool-menu-item .dropdown-item-indicator) {
        display: none;
    }

    .tool-item-main {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        min-width: 0;
        flex: 1;
    }

    .tool-item-text {
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex: 1;
    }

    /* Leading is set on the lines themselves, not the column: the menu row sets its own,
       and an inherited value loses to it. Matches `ToolRow` in the `/` menu. */
    .tool-item-label,
    .tool-item-description {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        line-height: var(--line-height-tight);
    }

    .tool-item-description {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    /* The favourite only shows on the row the user is pointing at (a favourite row keeps its own
       button visible), so the list stays quiet when nothing is being aimed at. */
    :global(.dropdown-checkbox-item.tool-menu-item:hover .menu-pin-button),
    :global(.dropdown-checkbox-item.tool-menu-item:focus-within .menu-pin-button) {
        opacity: 1;
    }

    /* In the dropdown the info chevron stays out of sight until the row is pointed at or
       focused, as in `ModelMenuListItem`; the mobile sheet has no hover, so it stays there. */
    :global(.dropdown-content--dropdown .dropdown-checkbox-item.tool-menu-item .tool-item-info) {
        opacity: 0;
    }

    :global(.dropdown-content .dropdown-checkbox-item.tool-menu-item:is(:hover, :focus-within, [data-highlighted]) .tool-item-info) {
        opacity: 1;
    }

    .tool-item-info {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        padding: 6px 0;
        margin: 0;
        border: none;
        background: none;
        pointer-events: all;
        line-height: 0;
        color: var(--color-text-muted);
        cursor: pointer;
    }

    .tool-item-info:hover {
        color: var(--color-text);
    }
</style>
