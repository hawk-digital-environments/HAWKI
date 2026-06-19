<script lang="ts">
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import Button from '$lib/components/ui/button/Button.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {Ellipsis, Pencil, RefreshCw, Spool, XIcon} from '@lucide/svelte';
    import {__} from '$lib/utils/translator.js';

    const composerContext = useComposerContext();

    const panelTitel = $derived.by(() => {
        if (composerContext.mode.isEdit) {
            return __('chat.composer.modePanel.editTitle');
        }
        if (composerContext.mode.isRegen) {
            return __('chat.composer.modePanel.regenTitle');
        }
        if (composerContext.mode.isThread) {
            return __('chat.composer.modePanel.threadTitle');
        }
        return __('chat.composer.modePanel.defaultTitle');
    });

    const panelContent = $derived.by(() => {
        if (composerContext.mode.isEdit) {
            return composerContext.mode.getState('edit').originalMessage;
        }
        if (composerContext.mode.isRegen) {
            return composerContext.mode.getState('regen').originalMessage;
        }

        return '';
    });

    const PanelIcon = $derived.by(() => {
        if (composerContext.mode.isEdit) {
            return Pencil;
        }
        if (composerContext.mode.isRegen) {
            return RefreshCw;
        }
        if (composerContext.mode.isThread) {
            return Spool;
        }
        return Ellipsis;
    });

    const cancelButtonTitle = $derived.by(() => {
        if (composerContext.mode.isEdit) {
            return __('chat.composer.modePanel.cancelEdit');
        }
        if (composerContext.mode.isRegen) {
            return __('chat.composer.modePanel.cancelRegen');
        }
        if (composerContext.mode.isThread) {
            return __('chat.composer.modePanel.cancelThread');
        }
        return __('chat.composer.modePanel.cancelDefault');
    });

    function cutText(text: string, maxLength: number): string {
        if (text.length <= maxLength) {
            return text;
        }
        return text.slice(0, maxLength) + '...';
    }
</script>
{#if !composerContext.mode.isDefault}
    <div class="panel" transition:growTransition>
        <div class="iconContent">
            <PanelIcon size={20}/>
            <div class="content">
                <span class="title">{panelTitel}</span>
                {cutText(panelContent, 100)}
            </div>
        </div>
        <Button
            iconRight={XIcon}
            disabled={composerContext.sendStatus?.sending}
            onclick={() => composerContext.mode.exit()}
            title={cancelButtonTitle}
            variant="ghost"
            size="xs"
        />
    </div>
{/if}

<style>
    .panel {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-2);
        background-color: var(--color-surface-raised);
        border: var(--border);
        border-radius: var(--corner-md);
        font-size: var(--font-size-xs);
        margin: var(--space-2);
        margin-bottom: 0;

        .iconContent {
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .content {
            color: var(--color-text-muted);
            overflow: hidden;
        }

        .title {
            display: block;
            font-weight: var(--font-weight-medium);
            margin-right: var(--space-1);
            color: var(--color-text);
        }
    }

</style>
