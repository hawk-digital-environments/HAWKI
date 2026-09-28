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
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {reportAttachmentIssues} from '$plugins/core/modules/chat/components/utils/attachmentIssues.js';

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
            <div class="chat-drop-fan" aria-hidden="true">
                <span class="chat-drop-page chat-drop-page--3"></span>
                <span class="chat-drop-page chat-drop-page--2"></span>
                <span class="chat-drop-page chat-drop-page--1"></span>
                <span class="chat-drop-page chat-drop-page--0"></span>
            </div>
            <span class="chat-drop-label">{translator.translate('chat.composer.fileDrop.dropLabel')}</span>
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
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: var(--space-3, calc(0.25rem * 3));
        border-radius: var(--corner-lg);
        background-color: var(--card-bg);
        color: color-mix(in oklch, var(--color-text-muted) 65%, transparent);
        font-weight: 500;
        pointer-events: none;
    }

    .chat-drop-label {
        animation: composer-section-slide-up var(--duration-fast, 300ms) var(--easing-spring) both;
        animation-delay: 80ms;
    }

    /* ── Springy fan of pages ─────────────────────────────────────────── */

    .chat-drop-fan {
        position: relative;
        width: 2.25rem;
        height: 2.75rem;
    }

    .chat-drop-page {
        position: absolute;
        inset: 0;
        border-radius: calc(var(--corner-md) * 0.75);
        border: 1.5px solid var(--color-border);
        background-color: color-mix(in oklch, var(--color-surface-raised) 96%, var(--color-text-muted));
        box-shadow: 0 2px 6px color-mix(in oklch, var(--color-text-muted) 12%, transparent);
        transform-origin: bottom center;
        /* Overshoot easing so each page springs slightly past its resting
           angle and settles back — a bouncier feel than the token spring. */
        animation: composer-page-fan 560ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }

    /* Each page settles at its own angle/offset, staggered for a cascading
       "fan" feel. --fan-rot is the resting rotation the spring lands on. */
    .chat-drop-page--0 {
        --fan-rot: 0deg;
        --fan-x: 0;
        --fan-y: 0;
        animation-delay: 40ms;
    }

    .chat-drop-page--1 {
        --fan-rot: 13deg;
        --fan-x: 0.32rem;
        --fan-y: -0.1rem;
        animation-delay: 80ms;
    }

    .chat-drop-page--2 {
        --fan-rot: 26deg;
        --fan-x: 0.6rem;
        --fan-y: -0.18rem;
        animation-delay: 120ms;
    }

    .chat-drop-page--3 {
        --fan-rot: 39deg;
        --fan-x: 0.82rem;
        --fan-y: -0.22rem;
        animation-delay: 160ms;
    }

    @keyframes composer-page-fan {
        from {
            opacity: 0;
            transform: translate(0, 0.5rem) rotate(0deg) scale(0.7);
        }

        to {
            opacity: 1;
            transform: translate(var(--fan-x), var(--fan-y)) rotate(var(--fan-rot)) scale(1);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .chat-drop-page {
            animation: none;
            opacity: 1;
            transform: translate(var(--fan-x), var(--fan-y)) rotate(var(--fan-rot));
        }
    }
</style>
