<!--
  @component Listens for OS-level file drags anywhere in the window
  (`svelte:window` drag events) and, on drop, adds the dropped files to
  `ComposerContext.attachments` (reporting any rejected files as toasts via
  `reportAttachmentIssues`, same as `FilePicker`). Does not render a visual
  drop target itself — it exposes `isDragging` and a `dragOverlay` snippet
  through its render-prop `children`, so the parent decides where/how to show
  the "drop files here" affordance (typically absolutely positioned over the
  composer card).

  Drag state uses a depth counter (`dragDepth`) rather than a boolean so that
  dragging over nested child elements (which fires `dragleave`/`dragenter`
  pairs) doesn't flicker `isDragging` off between children.

  @example
  ```svelte
  <FileDragAndDrop>
      {#snippet children({isDragging, dragOverlay})}
          <div class="chat-composer-card">
              {@render dragOverlay()}
              <div class:chat-composer-body--hidden={isDragging}>
                  // normal composer content
              </div>
          </div>
      {/snippet}
  </FileDragAndDrop>
  ```
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {reportAttachmentIssues} from '$plugins/core/modules/chat/components/utils/attachmentIssues.js';
    import {FILE_UPLOAD_ANNOUNCEMENT_ANCHOR} from '$plugins/core/stores/AnnouncementStore.svelte.js';
    import FileDropHint from '$lib/components/ui/file-drop/FileDropHint.svelte';

    interface Props {
        /**
         * Render-prop snippet receiving the current drag state and a `dragOverlay`
         * snippet to render wherever the "drop files here" hint should appear.
         * See the component example above.
         */
        children?: (args: {
            /** `true` while a file drag is over the window; drives showing `dragOverlay`
             *  and (typically) hiding/dimming the composer's normal content. */
            isDragging: boolean;
            /** Snippet rendering the animated drop-hint overlay. Only paints anything
             *  while `isDragging` is `true`; call it unconditionally where you want the
             *  overlay positioned. */
            dragOverlay: Snippet;
        }) => any;
    }

    const {
        children
    }: Props = $props();

    const composerContext = useComposerContext();
    const toastContext = useToastContext();
    const translator = useTranslator();
    const announcementStore = useStore('announcements');

    let isDragging = $state(false);
    let dragDepth = 0;

    function hasFilePayload(e: DragEvent) {
        return Array.from(e.dataTransfer?.types ?? []).includes('Files');
    }

    function handleDragEnter(e: DragEvent) {
        if (!hasFilePayload(e)) return;
        e.preventDefault();
        dragDepth++;
        isDragging = true;
    }

    function handleDragOver(e: DragEvent) {
        if (!hasFilePayload(e)) return;
        e.preventDefault();
        if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
    }

    function handleDragLeave(e: DragEvent) {
        if (!hasFilePayload(e)) return;
        dragDepth--;
        if (dragDepth <= 0) {
            dragDepth = 0;
            isDragging = false;
        }
    }

    function handleDrop(e: DragEvent) {
        if (!hasFilePayload(e)) return;
        e.preventDefault();
        dragDepth = 0;
        isDragging = false;
        const files = e.dataTransfer?.files;
        if (files?.length) {
            reportAttachmentIssues(translator, toastContext, composerContext.attachments.add(files));
            announcementStore.triggerAnchor(FILE_UPLOAD_ANNOUNCEMENT_ANCHOR);
        }
    }
</script>

<svelte:window
    ondragenter={handleDragEnter}
    ondragover={handleDragOver}
    ondragleave={handleDragLeave}
    ondrop={handleDrop}
/>

{#snippet dragOverlay()}
    {#if isDragging}
        <div class="chat-drop-overlay">
            <FileDropHint label={translator.translate('chat.composer.fileDrop.dropLabel')}/>
        </div>
    {/if}
{/snippet}

{@render children?.({
    isDragging,
    dragOverlay
})}


<style>
    /* ── Drag-and-drop overlay ────────────────────────────────────────── */

    .chat-drop-overlay {
        position: absolute;
        inset: 0;
        border-radius: var(--corner-lg);
        background-color: var(--card-bg);
        pointer-events: none;
    }
</style>
