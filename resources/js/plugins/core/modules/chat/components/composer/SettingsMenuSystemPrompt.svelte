<!--
  @component System prompt section of the `SettingsMenu`: heading with a reset
  button, a one-line preview of `composerContext.systemPrompt`, and the
  `SystemPromptDialog` that opens on click for full editing. The reset button
  restores the `system-prompts` store's `'default'` entry and is only enabled
  while the prompt differs from it. Takes no props — reads/writes
  `ComposerContext` directly.
-->
<script lang="ts">
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import SystemPromptDialog from '$plugins/core/modules/chat/components/composer/SystemPromptDialog.svelte';
    import SettingsMenuSectionHeader from '$plugins/core/modules/chat/components/composer/SettingsMenuSectionHeader.svelte';
    import PencilEdit01Icon from '$lib/components/ui/icons/iconset/PencilEdit01Icon.svelte';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const composerContext = useComposerContext();
    const systemPromptStore = useStore('system-prompts');
    const {__} = useTranslator();

    let dialogOpen = $state(false);

    const defaultSystemPrompt = $derived.by(() => systemPromptStore.getPromptByType('default').prompt);
    const hasCustomSystemPrompt = $derived.by(() => composerContext.systemPrompt !== defaultSystemPrompt);
</script>

<div class="system-prompt-section">
    <SettingsMenuSectionHeader
        title={__('chat.composer.settings.systemPromptHeading')}
        resetTooltip={__('chat.composer.settings.resetSystemPrompt')}
        resetDisabled={!hasCustomSystemPrompt}
        onReset={() => (composerContext.systemPrompt = defaultSystemPrompt)}
    />
    <button type="button" class="system-prompt-preview" onclick={() => (dialogOpen = true)}>
        <PencilEdit01Icon size={14} class="system-prompt-icon"/>
        <span class="system-prompt-text">
            {composerContext.systemPrompt?.trim() ? composerContext.systemPrompt : __('chat.composer.settings.noSystemPrompt')}
        </span>
    </button>
</div>

<SystemPromptDialog
    bind:open={dialogOpen}
    value={composerContext.systemPrompt ?? ''}
    onChange={(newPrompt) => { composerContext.systemPrompt = newPrompt; }}
/>

<style>
    .system-prompt-section {
        display: flex;
        flex-direction: column;
        gap: var(--space-2, calc(0.25rem * 2));
    }

    .system-prompt-preview {
        display: flex;
        align-items: center;
        gap: var(--space-2, calc(0.25rem * 2));
        width: 100%;
        height: calc((var(--font-size-xs) * var(--line-height-tight)) + var(--space-4));
        box-sizing: border-box;
        padding: 0 var(--space-2, calc(0.25rem * 2));
        border: var(--border);
        border-radius: var(--corner-sm);
        background: transparent;
        color: var(--color-text);
        cursor: pointer;
        text-align: left;
        transition: border-color var(--duration-fast, 150ms) var(--easing-default);
    }

    .system-prompt-preview:hover {
        border-color: var(--color-text-muted);
    }

    :global(.system-prompt-icon) {
        flex-shrink: 0;
        color: var(--color-text-muted);
    }

    .system-prompt-text {
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        font-size: var(--font-size-xs);
        line-height: var(--line-height-tight);
    }
</style>
