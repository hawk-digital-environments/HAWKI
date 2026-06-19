<script lang="ts">
    import DropdownMenuCheckboxItem from '$lib/components/ui/dropdown-menu/DropdownMenuCheckboxItem.svelte';
    import type {ToolMenuEntry} from '$lib/components/chat/composer/ToolMenu.svelte';
    import ToolIcon from '$lib/components/chat/composer/utils/ToolIcon.svelte';
    import {useToolMenuFocusContext} from '$lib/components/chat/composer/contexts/ToolMenuFocusContext.svelte.js';
    import {Check, ChevronRight} from '@lucide/svelte';
    import StatusDotForTool from '$lib/components/chat/composer/StatusDotForTool.svelte';
    import {__} from '$lib/utils/translator.js';

    interface Props {
        entry: ToolMenuEntry;
        onOpenDetail?: (entry: ToolMenuEntry) => void;
    }

    const {entry, onOpenDetail}: Props = $props();
    const focusContext = useToolMenuFocusContext();

    let rowEl = $state<HTMLDivElement | null>(null);

    // Tints the info trigger to match the status surfaced in the detail view.
    const status = $derived(
        entry.disabled ? 'error' : !entry.supported ? 'warning' : 'available'
    );

    const infoTooltip = $derived.by(() => {
        if (entry.disabled) return __('chat.composer.toolMenu.infoOffline');
        if (!entry.supported) return __('chat.composer.toolMenu.infoUnsupported');
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
    closeOnSelect={false}
    onCheckedChange={entry.onChange}
    disabled={entry.disabled}
    onkeydown={onRowKeydown}
    data-tool-name={entry.tool.name}
    aria-keyshortcuts="ArrowRight"
    class="tool-menu-item">
    {#snippet children(checked)}
        <span class="tool-item-main">
            <ToolIcon tool={entry.tool}/>
            <span class="tool-item-label">{entry.name}</span>
            <span class="tool-item-check">
                {#if checked}
                    <Check size={12}/>
                {/if}
            </span>

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
                    supported={entry.supported}
                    tooltipSuffix={__('chat.composer.toolMenu.clickForInfo')}
                />
                <ChevronRight size={10}/>
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
        /* Check now lives in the row flow, so drop the reserved right padding. */
        padding-right: var(--space-2, calc(0.25rem * 2));
    }

    :global(.dropdown-checkbox-item.tool-menu-item[data-disabled]) {
        cursor: not-allowed;
    }

    /* Built-in absolute check is replaced by the in-flow one below. */
    :global(.dropdown-checkbox-item.tool-menu-item .dropdown-item-indicator) {
        display: none;
    }

    .tool-item-check {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: calc(0.25rem * 3.5);
        height: calc(0.25rem * 3.5);
        color: var(--color-text);
    }

    .tool-item-main {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        min-width: 0;
        flex: 1;
    }

    .tool-item-label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .tool-item-info {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        padding: 0;
        margin: 0;
        border: none;
        background: none;
        line-height: 0;
        color: var(--color-text-muted);
        cursor: pointer;
        transition: color var(--duration-fast, 150ms), opacity var(--duration-fast, 150ms);
    }

    .tool-item-info--warning {
        color: var(--color-warning, var(--color-text-muted));
    }

    .tool-item-info--error {
        color: var(--color-error, var(--color-text-muted));
    }

    .tool-item-info--available:hover {
        color: var(--color-text);
    }

    .tool-item-info--warning:hover,
    .tool-item-info--error:hover {
        opacity: 0.75;
    }
</style>
