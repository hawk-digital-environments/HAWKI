<script lang="ts">
    import {onMount} from 'svelte';
    import {ArrowLeft} from '@lucide/svelte';
    import type {ToolMenuEntry} from './ToolMenu.svelte';
    import ToolIcon from '$lib/components/chat/composer/utils/ToolIcon.svelte';
    import Switch from '$lib/components/ui/switch/Switch.svelte';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import StatusDotForTool from '$lib/components/chat/composer/StatusDotForTool.svelte';
    import {__} from '$lib/utils/translator.js';

    const composerContext = useComposerContext();

    interface Props {
        entry: ToolMenuEntry;
        onCloseDetail: () => void;
    }

    let {
        entry,
        onCloseDetail
    }: Props = $props();

    let detailEl = $state<HTMLDivElement | null>(null);
    let backEl = $state<HTMLButtonElement | null>(null);
    let toggleEl = $state<HTMLButtonElement | null>(null);

    // Move focus into the panel when it opens, landing on the primary control
    // (the toggle, or Back when the tool can't be toggled).
    onMount(() => {
        const target = toggleEl && !entry.disabled ? toggleEl : backEl;
        const raf = requestAnimationFrame(() => target?.focus());
        return () => cancelAnimationFrame(raf);
    });

    function focusables(): HTMLElement[] {
        if (!detailEl) return [];
        return Array.from(detailEl.querySelectorAll<HTMLElement>('button:not([disabled])'));
    }

    function moveFocus(direction: 1 | -1) {
        const items = focusables();
        if (items.length === 0) return;
        const current = items.indexOf(document.activeElement as HTMLElement);
        const next = (current + direction + items.length) % items.length;
        items[next].focus();
    }

    // The panel lives inside a bits-ui menu whose keyboard model (arrow roving,
    // Tab-to-close, Escape-to-close) doesn't fit a sub-panel. Stop the handled
    // keys from reaching it and drive focus/back navigation ourselves.
    function onDetailKeydown(event: KeyboardEvent) {
        switch (event.key) {
            case 'Escape':
            case 'ArrowLeft':
                event.preventDefault();
                event.stopPropagation();
                onCloseDetail?.();
                break;
            case 'ArrowDown':
                event.preventDefault();
                event.stopPropagation();
                moveFocus(1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                event.stopPropagation();
                moveFocus(-1);
                break;
            case 'Tab':
                event.preventDefault();
                event.stopPropagation();
                moveFocus(event.shiftKey ? -1 : 1);
                break;
        }
    }

    function toggleActive() {
        if (entry.disabled) return;
        entry.onChange(!entry.active);
    }
</script>
<!--
  Container-level keydown only delegates focus/back navigation to the child
  buttons (Back, toggle), which carry their own roles. It is not an interactive
  widget itself.
-->
<!-- svelte-ignore a11y_no_static_element_interactions -->
<div class="tool-detail" bind:this={detailEl} onkeydown={onDetailKeydown}>
    <button
        type="button"
        class="tool-detail-back"
        bind:this={backEl}
        onpointerdowncapture={(e) => e.stopPropagation()}
        onclick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    onCloseDetail?.();
                }}>
        <ArrowLeft size={14}/>
        <span>{__('chat.composer.toolMenu.backButton')}</span>
    </button>

    <div class="tool-detail-header">
        <span class="tool-detail-title">
            <ToolIcon tool={entry.tool}/>
            <span class="tool-detail-name">{entry.name}</span>
        </span>
        <button
            type="button"
            class="tool-detail-toggle"
            role="switch"
            bind:this={toggleEl}
            aria-label={entry.active ? __('chat.composer.toolMenu.deactivateTool') : __('chat.composer.toolMenu.activateTool')}
            aria-checked={entry.active ? 'true' : 'false'}
            disabled={entry.disabled}
            onpointerdowncapture={(e) => e.stopPropagation()}
            onclick={toggleActive}>
            <Switch checked={entry.active} disabled={entry.disabled} presentational/>
        </button>
    </div>

    <div class="tool-detail-status">
        <StatusDotForTool tool={entry.tool} supported={entry.supported} showLabel/>
    </div>

    {#if entry.description}
        <p class="tool-detail-description">{entry.description}</p>
    {/if}
</div>

<style>
    .tool-detail {
        display: flex;
        flex-direction: column;
        gap: var(--space-1_5);
        min-width: 0;
        padding-bottom: var(--space-2, calc(0.25rem * 2));
    }

    .tool-detail-back {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1, 0.25rem);
        align-self: flex-start;
        margin-bottom: var(--space-1, 0.25rem);
        padding: var(--space-1, 0.25rem) var(--space-2, calc(0.25rem * 2));
        border: none;
        border-radius: var(--corner-sm);
        background: none;
        color: var(--color-text-muted, var(--color-text));
        font-size: var(--font-size-xs);
        cursor: pointer;
        transition: background-color var(--duration-fast, 150ms);
    }

    .tool-detail-back:hover {
        background-color: var(--color-hover);
        color: var(--color-text);
    }

    .tool-detail-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2, calc(0.25rem * 2));
        padding-inline: var(--space-2, calc(0.25rem * 2));
    }

    .tool-detail-title {
        display: inline-flex;
        align-items: center;
        gap: var(--space-2, calc(0.25rem * 2));
        min-width: 0;
    }

    .tool-detail-name {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium, 500);
        color: var(--color-text);
    }

    .tool-detail-toggle {
        display: inline-flex;
        flex-shrink: 0;
        padding: 0;
        border: none;
        background: none;
        cursor: pointer;
    }

    .tool-detail-toggle:disabled {
        cursor: not-allowed;
    }

    .tool-detail-status {
        padding-inline: var(--space-2, calc(0.25rem * 2));
        font-size: var(--font-size-xxs);
    }

    .tool-detail-description {
        margin: 0;
        padding-inline: var(--space-2, calc(0.25rem * 2));
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted, var(--color-text));
    }
</style>
