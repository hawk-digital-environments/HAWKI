<!--
  @component Paperclip button that opens a hidden file-input for attaching
  multiple files to a chat message.
-->
<script lang="ts">
    import {Paperclip} from '@lucide/svelte';
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import {useComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import {reportAttachmentIssues} from '$lib/components/chat/utils/attachmentIssues.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {__} from '$lib/utils/translator.js';

    const composerContext = useComposerContext();
    const toastContext = useToastContext();

    let inputEl: HTMLInputElement;
    let isAdding = $state(false);

    const supportedMimeTypes = $derived.by(() => composerContext.attachments.allowedMimeTypes.join(','));

    function openFilePicker() {
        isAdding = true;
        inputEl.click();
    }

    function handleChange(e: Event) {
        const target = e.target as HTMLInputElement;
        if (target.files?.length) {
            reportAttachmentIssues(toastContext, composerContext.attachments.add(target.files));
            target.value = '';
        }
        isAdding = false;
    }

    function handleCancel() {
        isAdding = false;
    }
</script>

<ButtonWithTooltip
    tooltip={__('chat.composer.attachFileTooltip')}
    variant="ghost"
    disabled={composerContext.guard.disablesFeature('attachments')}
    iconLeft={Paperclip}
    highlight={isAdding}
    onclick={openFilePicker}
/>

<input
    bind:this={inputEl}
    type="file"
    accept={supportedMimeTypes}
    multiple
    class="file-input-hidden"
    onchange={handleChange}
    oncancel={handleCancel}
/>

<style>
    .file-input-hidden {
        display: none;
    }
</style>
