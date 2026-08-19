<!--
  @component One row of the chat sidebar's history list: the conversation row
  itself plus an actions menu (rename / delete) that only appears while the row
  is hovered, focused, or its menu is open.

  Renaming happens inline — the row is swapped for a text field that commits on
  Enter / blur and cancels on Escape; deleting goes through a `ConfirmDialog`.
  Both actions are reported through `onRename` / `onDelete`, so the page owns
  the store calls and error handling.
-->
<script lang="ts">
    import SidebarItem from '$lib/components/ui/sidebar/SidebarItem.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import MoreHorizontalIcon from '$lib/components/ui/icons/iconset/MoreHorizontalIcon.svelte';
    import PencilEdit01Icon from '$lib/components/ui/icons/iconset/PencilEdit01Icon.svelte';
    import Delete02Icon from '$lib/components/ui/icons/iconset/Delete02Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import type {Snippet} from 'svelte';

    interface Props {
        name: string;
        active?: boolean;
        /** Leading visual, e.g. the "still generating" indicator. */
        media?: Snippet;
        /** Accessible name of the row, defaults to the conversation name. */
        rowLabel?: string;
        onOpen: () => void;
        onRename: (name: string) => void | Promise<void>;
        onDelete: () => void | Promise<void>;
    }

    const {name, active = false, media, rowLabel, onOpen, onRename, onDelete}: Props = $props();
    const {__} = useTranslator();
    const toast = useToastContext();

    let menuOpen = $state(false);
    let deleteOpen = $state(false);
    let renaming = $state(false);
    let renameInput = $state<HTMLInputElement | null>(null);

    function commitRename(nextName: string) {
        if (!renaming) return;
        renaming = false;
        const trimmed = nextName.trim();
        if (!trimmed || trimmed === name) return;
        void onRename(trimmed);
    }

    function onRenameKeyDown(event: KeyboardEvent) {
        const input = event.target as HTMLInputElement;
        if (event.key === 'Enter') {
            event.preventDefault();
            if (!input.value.trim()) {
                toast.error(__('chat.nameMenu.emptyNameError'));
                return;
            }
            commitRename(input.value);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            renaming = false;
        }
    }

    $effect(() => {
        if (renaming && renameInput) {
            renameInput.focus();
            renameInput.select();
        }
    });
</script>

<div class="history-row" class:menu-open={menuOpen} class:active>
    {#if renaming}
        <input
            class="rename-input"
            bind:this={renameInput}
            value={name}
            aria-label={__('chat.nameMenu.newNameAriaLabel')}
            onkeydown={onRenameKeyDown}
            onblur={event => commitRename((event.target as HTMLInputElement).value)}
        />
    {:else}
        <SidebarItem
            {media}
            {active}
            label={name}
            aria-label={rowLabel ?? name}
            onclick={onOpen}
        />
        <!-- Sits on top of the row rather than inside it: a nav row is a
             button, so its trigger cannot be nested in the same element. -->
        <div class="actions">
            <DropdownMenu bind:open={menuOpen} align="end">
                {#snippet trigger({props})}
                    <ButtonWithTooltip
                        {...props}
                        variant="ghost"
                        size="sm"
                        iconLeft={MoreHorizontalIcon}
                        tooltip={__('chat.nameMenu.actionsTooltip')}
                        tooltipSide="right"
                    />
                {/snippet}
                <DropdownMenuItem icon={PencilEdit01Icon} onclick={() => renaming = true}>
                    {__('chat.nameMenu.rename')}
                </DropdownMenuItem>
                <DropdownMenuItem icon={Delete02Icon} variant="destructive" onclick={() => deleteOpen = true}>
                    {__('chat.nameMenu.deleteAction')}
                </DropdownMenuItem>
            </DropdownMenu>
        </div>
    {/if}
</div>

<ConfirmDialog
    bind:open={deleteOpen}
    title={__('chat.nameMenu.deleteConfirmTitle', {name})}
    description={__('chat.nameMenu.deleteConfirmDescription')}
    onConfirm={onDelete}
/>

<style>
    .history-row {
        /* The trigger sits inside the row's rounded rectangle with the same gap
           on every side, one radius step tighter than the row. */
        --action-gap: var(--space-1);
        --action-size: calc(var(--nav-row-h) - 2 * var(--action-gap));
        position: relative;
    }

    /* The label has to stop before the trigger, otherwise a long chat name
       runs underneath it. */
    .history-row :global(.sidebar-item) {
        padding-right: calc(var(--nav-row-h) - var(--action-gap));
    }

    /* Spans the row's full height and centres the trigger in it, rather than
       being pinned to a computed midpoint. */
    .actions {
        position: absolute;
        top: 0;
        bottom: 0;
        right: var(--action-gap);
        z-index: 2;
        display: flex;
        align-items: center;
        /* Hidden at rest — the list stays quiet until a row is pointed at. */
        opacity: 0;
        pointer-events: none;
        transition: opacity var(--duration-fast);
    }

    .history-row:hover .actions,
    .history-row:focus-within .actions,
    .history-row.menu-open .actions {
        opacity: 1;
        pointer-events: auto;
    }

    .actions :global(button) {
        width: var(--action-size);
        height: var(--action-size);
        padding: 0;
        border-radius: var(--corner-xs);
        /* No plate behind the glyph — the row underneath already carries the
           hover / active surface. */
        background: transparent;
    }

    /* The glyph sits directly on the selected row's surface, so it takes the
       same text color as the row's label rather than the neutral ghost color. */
    .history-row.active .actions :global(button) {
        color: var(--color-active-text);
    }

    /* Touch has no hover, so the trigger stays visible there. */
    @media (hover: none) {
        .actions {
            opacity: 1;
            pointer-events: auto;
        }
    }

    /* Inline field in place of the row; matches the row's metrics so the list
       doesn't jump while renaming. */
    .rename-input {
        width: 100%;
        min-height: var(--nav-row-h);
        padding: 0 var(--space-2_5) 0 var(--nav-item-pad-x);
        border: 1px solid var(--color-focus-ring);
        border-radius: var(--corner-sm);
        background: var(--color-surface-raised);
        color: var(--color-text);
        font: inherit;
        font-size: var(--font-size-nav);
    }

    .rename-input:focus,
    .rename-input:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px color-mix(in oklab, var(--color-focus-ring) 25%, transparent);
    }
</style>
