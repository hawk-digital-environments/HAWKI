<script lang="ts">
    import ChatNameMenu from '$plugins/core/modules/chat/components/nameMenu/ChatNameMenu.svelte';
    import ExportMenu from '$plugins/core/modules/chat/components/header/ExportMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import type {OldUiExportType} from '$lib/legacy/OldUiBridge.svelte.js';
    import type {ChatConversation} from '$plugins/core/modules/chat/types.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        conversation: ChatConversation;
        onRename: (name: string) => void | Promise<void>;
        onDelete: () => void | Promise<void>;
        onExport: (format: OldUiExportType) => void;
        generating?: boolean;
        /** When set, renders a visually hidden skip link (after the title) that jumps focus past the message log into the composer. */
        onSkipToComposer?: () => void;
    }

    const {conversation, onRename, onDelete, onExport, generating = false, onSkipToComposer}: Props = $props();
    const {__} = useTranslator();
    let name = $state((() => conversation.name)());
    let deleteOpen = $state(false);

    $effect(() => {
        name = conversation.name;
    });
</script>

<header>
    <h1 class="u-sr-only">{conversation.name}</h1>
    <div class="name">
        <ChatNameMenu
            bind:name
            slug={conversation.slug}
            nameClickRenames
            onNameChange={(_, nextName) => onRename(nextName)}
        >
            <DropdownMenuItem variant="destructive" onclick={() => deleteOpen = true}>
                {__('chat.nameMenu.deleteAction')}
            </DropdownMenuItem>
        </ChatNameMenu>
    </div>
    {#if onSkipToComposer}
        <button class="skip-to-composer" type="button" onclick={onSkipToComposer}>
            {__('chat.page.skipToComposer')}
        </button>
    {/if}
    {#if generating}
        <div class="generating" role="status" aria-live="polite">
            <span class="generating-spinner" aria-hidden="true"></span>
            <span>{__('chat.page.generating')}</span>
        </div>
    {/if}
    <ExportMenu {onExport} />
</header>

<ConfirmDialog
    bind:open={deleteOpen}
    title={__('chat.nameMenu.deleteConfirmTitle', {name})}
    description={__('chat.nameMenu.deleteConfirmDescription')}
    onConfirm={onDelete}
/>

<style>
    header {
        position: relative;
        /* Above the scroll region so the fade can overhang the messages. */
        z-index: 1;
        display: flex;
        min-height: 3.75rem;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-3);
        padding: var(--space-2) var(--space-5);
    }

    /* Soft fade instead of a hard divider: the blurred panel backdrop is drawn
       on a pseudo-element that extends past the header and fades out, so
       content scrolling underneath dissolves rather than hitting a line. */
    header::before {
        content: '';
        position: absolute;
        inset: 0 0 -3rem;
        z-index: -1;
        pointer-events: none;
        background: color-mix(in oklch, var(--panel-bg) 88%, transparent);
        backdrop-filter: blur(12px);
        /* Eased ramp (rather than one linear stop) so neither the start nor the
           end of the fade shows a visible edge. */
        --header-fade: linear-gradient(
            to bottom,
            black 0,
            black 45%,
            rgba(0, 0, 0, 0.86) 60%,
            rgba(0, 0, 0, 0.55) 72%,
            rgba(0, 0, 0, 0.25) 84%,
            rgba(0, 0, 0, 0.08) 92%,
            transparent 100%
        );
        mask-image: var(--header-fade);
        -webkit-mask-image: var(--header-fade);
    }

    .name {
        flex: 1;
        min-width: 0;
        max-width: 34rem;
        font-size: var(--font-size-nav);
        font-weight: var(--font-weight-medium);
    }

    /* Like the app's skip link: invisible until keyboard focus, then a small
       pill below the header so it never shifts the header layout. */
    .skip-to-composer {
        position: absolute;
        top: calc(100% + var(--space-2));
        left: var(--space-5);
        z-index: 10;
        padding: var(--space-2) var(--space-3);
        border: none;
        border-radius: var(--corner-sm);
        background: var(--color-interactive);
        color: var(--color-on-interactive);
        font-weight: var(--font-weight-semibold);
        cursor: pointer;
    }

    .skip-to-composer:not(:focus) {
        width: 1px;
        height: 1px;
        padding: 0;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
    }

    .generating {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1_5);
        flex-shrink: 0;
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    .generating-spinner {
        width: 0.75rem;
        height: 0.75rem;
        border: 2px solid var(--color-border);
        border-top-color: var(--color-interactive);
        border-radius: 50%;
        animation: generation-spin 700ms linear infinite;
    }

    @keyframes generation-spin { to { transform: rotate(360deg); } }

    @media (prefers-reduced-motion: reduce) {
        .generating-spinner {
            border-color: var(--color-interactive);
            animation: none;
        }
    }

    @media (--bp-md-and-smaller) {
        header {
            padding-right: var(--space-3);
            padding-left: calc(var(--space-3) + 2.75rem);
        }
    }
</style>
