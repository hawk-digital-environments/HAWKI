<!--
  @component A single toggleable tool row inside `ToolMenuList`'s `DropdownMenuCheckboxItem`
  list: the tool's icon on a swatch, its name, and its description truncated to one line —
  the same row shape the composer's `/` menu shows via `ToolRow`.

  The whole row toggles the tool (checkbox semantics, wired to `entry.onToggle`); a
  separate info button on the right — tinted by status (available/warning/error) — opens
  `ToolMenuDetail` for that tool instead, stopping propagation so it never also toggles.

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

    // Tints the info trigger to match the status surfaced in the detail view.
    const status = $derived(
        entry.disabled ? 'error' : !entry.available ? 'warning' : 'available'
    );

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
        if (event.key === 'Tab') {
            if (focusContext.focusAdjacent(entry.tool.name, event.shiftKey ? -1 : 1)) {
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
    indicator="none"
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
            <MenuPinButton kind="tool" id={entry.tool.name}/>
            <button
                type="button"
                class={['tool-item-info', `tool-item-info--${status}`]}
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
                <StatusDotForTool
                    tool={entry.tool}
                    supported={entry.available}
                    tooltipSuffix={__('chat.composer.toolMenu.clickForInfo')}
                />
                <ArrowRight01Icon size={16}/>
            </button>

        </span>
    {/snippet}
</DropdownMenuCheckboxItem>

<style>
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

    .tool-item-info {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        padding: 6px 0;
        margin: 0;
        border: none;
        background-color: rgba(255, 0, 0, 0.001%);
        pointer-events: all;
        line-height: 0;
        color: var(--color-text-muted);
        cursor: pointer;
    }

    /* The chevron takes the same semantic color as the dot beside it (`StatusDot` maps
       online/unknown/offline to success/warning/error), so the pair reads as one signal
       instead of a tinted dot next to a grey arrow. */
    .tool-item-info--available {
        color: var(--color-success, var(--color-text-muted));
    }

    .tool-item-info--warning {
        color: var(--color-warning, var(--color-text-muted));
    }

    .tool-item-info--error {
        color: var(--color-error, var(--color-text-muted));
    }
</style>
