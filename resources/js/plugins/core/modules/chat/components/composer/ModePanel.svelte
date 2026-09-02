<!--
  @component Banner shown above the composer's model/settings row whenever the composer
  is in a non-default mode (`composerContext.mode.isEdit` / `isThread`).
  Shows a mode-specific icon, title, and a truncated preview of the message being edited
  (`mode.getState('edit').originalMessage`; empty for `thread`),
  plus a cancel button that calls `composerContext.mode.exit()` — restoring the
  checkpoint taken when the mode was entered, effectively undoing everything the mode did.

  Renders nothing while `composerContext.mode.isDefault` is true. Takes no props — it
  reads everything from `ComposerContext.mode`.

  ## Usage
  Rendered once by `ChatComposer.svelte`, above the model/settings row, so the banner
  can push the rest of the composer down when a mode is entered:
  ```svelte
  <ModePanel/>
  <div class="chat-composer-top-row">...</div>
  ```
-->
<script lang="ts">
    import Button from '$lib/components/ui/button/Button.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import MoreHorizontalIcon from '$lib/components/ui/icons/iconset/MoreHorizontalIcon.svelte';
    import ThreadIcon from '$lib/components/ui/icons/iconset/ThreadIcon.svelte';
    import PencilEdit01Icon from '$lib/components/ui/icons/iconset/PencilEdit01Icon.svelte';
    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const composerContext = useComposerContext();
    const {__} = useTranslator();

    const panelTitel = $derived.by(() => {
        if (composerContext.mode.isEdit) {
            return __('chat.composer.modePanel.editTitle');
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

        return '';
    });

    const PanelIcon = $derived.by(() => {
        if (composerContext.mode.isEdit) {
            return PencilEdit01Icon;
        }
        if (composerContext.mode.isThread) {
            return ThreadIcon;
        }
        return MoreHorizontalIcon;
    });

    const cancelButtonTitle = $derived.by(() => {
        if (composerContext.mode.isEdit) {
            return __('chat.composer.modePanel.cancelEdit');
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
            iconRight={Cancel01Icon}
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
