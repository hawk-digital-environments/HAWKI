<!--
  @component Paperclip button that opens a hidden file-input for attaching
  multiple files to a chat message. On selection, adds the files to
  `ComposerContext.attachments` and reports any rejected files (unsupported
  type / too large) as toasts via `reportAttachmentIssues` — the same
  reporting path used by `FileDragAndDrop`. Disabled when the current mode
  disables the `'attachments'` feature (see `GuardSlice.disablesFeature`).
  No props — reads `ComposerContext` directly.

  @example
  ```svelte
    // placed in the composer's bottom-left action row
  <FilePicker/>
  ```
-->
<script lang="ts">
    import ButtonWithTooltip from '$lib/components/ui/button/ButtonWithTooltip.svelte';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import AttachmentIcon from '$lib/components/ui/icons/iconset/AttachmentIcon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {reportAttachmentIssues} from '$plugins/core/modules/chat/components/utils/attachmentIssues.js';
    import {FILE_UPLOAD_ANNOUNCEMENT_ANCHOR} from '$plugins/core/stores/AnnouncementStore.svelte.js';

    const composerContext = useComposerContext();
    const toastContext = useToastContext();
    const translator = useTranslator();
    const announcementStore = useStore('announcements');

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
            reportAttachmentIssues(translator, toastContext, composerContext.attachments.add(target.files));
            announcementStore.triggerAnchor(FILE_UPLOAD_ANNOUNCEMENT_ANCHOR);
            target.value = '';
        }
        isAdding = false;
        composerContext.focusInput();
    }

    function handleCancel() {
        isAdding = false;
        composerContext.focusInput();
    }
</script>

<ButtonWithTooltip
    tooltip={translator.translate('chat.composer.attachFileTooltip')}
    variant="ghost"
    disabled={composerContext.guard.disablesFeature('attachments')}
    iconLeft={AttachmentIcon}
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
