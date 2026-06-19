<!--
  @component Modal dialog for viewing and editing the system prompt for the
  current chat session. Changes are applied only when the user confirms via
  the save button; closing or cancelling discards any unsaved edits.
-->
<script lang="ts">
    import {FileText} from '@lucide/svelte';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import {__} from '$lib/utils/translator.js';

    interface Props {
        /** Whether the dialog is open. Supports bind:open for two-way binding. */
        open?: boolean;
        /** Called when the dialog requests an open-state change. */
        onOpenChange?: (open: boolean) => void;
        /** Current system prompt value. */
        value: string;
        /** Called with the new value when the user saves. */
        onChange?: (value: string) => void;
    }

    let {open = $bindable(false), onOpenChange, value = $bindable(''), onChange}: Props = $props();

    let draft = $state('');

    $effect(() => {
        if (open) draft = value;
    });

    function handleOpenChange(isOpen: boolean) {
        if (isOpen) draft = value;
        open = isOpen;
        onOpenChange?.(isOpen);
    }

    function handleSave() {
        value = draft;
        onChange?.(draft);
        handleOpenChange(false);
    }
</script>
<Dialog
    open={open}
    onOpenChange={handleOpenChange}
    contentProps={{class: 'system-prompt-dialog-content'}}
>
    {#snippet title()}
        <FileText size={16} class="system-prompt-icon"/>
        {__('chat.composer.systemPromptDialog.title')}
    {/snippet}
    {#snippet description()}
        {__('chat.composer.systemPromptDialog.description')}
    {/snippet}
    {#snippet footer()}
        <Button variant="ghost" size="sm" onclick={() => handleOpenChange(false)}>
            {__('chat.composer.systemPromptDialog.cancel')}
        </Button>
        <Button size="sm" onclick={handleSave}>
            {__('chat.composer.systemPromptDialog.save')}
        </Button>
    {/snippet}
    <Textarea
        bind:value={draft}
        placeholder={__('chat.composer.systemPromptDialog.placeholder')}
        class="system-prompt-textarea"
    />
</Dialog>

<style>
    :global(.system-prompt-icon) {
        color: var(--color-text-muted);
        flex-shrink: 0;
    }

    :global(.system-prompt-dialog-content.system-prompt-dialog-content) {
        padding: var(--space-4);
        width: 90vw;
        max-width: 90vw;
        height: 80vh;
        max-height: 80vh;
        grid-template-rows: auto 1fr auto;
    }

    :global(textarea.system-prompt-textarea.system-prompt-textarea) {
        min-height: 160px;
        height: 100%;
        resize: none;
        border-radius: var(--corner-md);
    }
</style>
