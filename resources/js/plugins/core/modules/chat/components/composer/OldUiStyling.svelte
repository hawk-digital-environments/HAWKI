<!--
  @component Purely-imperative bridge between the new composer's mode system
  and the legacy chat log DOM (see `$lib/legacy/OldUiBridge`). The message
  log itself (the scrolling list of `.message` elements) still belongs to the
  old UI and hasn't been ported to Svelte yet; when the composer enters
  `edit`/`thread` mode, this component reaches into that legacy DOM
  via `document.querySelector`/`getElementById` to highlight the message
  being edited (or the thread being replied in), dim its
  controls, hide unrelated siblings, and scroll it into view — then reverts
  all of that when the mode exits.

  Renders no markup of its own (only a `<style>` block with the
  `old-ui-mode-*` global classes it toggles) — mount it once near the top of
  the composer tree purely for its `$effect`. No props; it derives everything
  from `ComposerContext.mode`.

  @example
  ```svelte
  <div class="chat-composer-wrapper">
      <OldUiStyling/>
      // rest of the composer
  </div>
  ```
-->
<script lang="ts">
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import type {ChatEditModeState} from '$plugins/core/modules/chat/components/composer/contexts/modes/ChatEditMode.js';
    import type {ChatThreadModeState} from '$plugins/core/modules/chat/components/composer/contexts/modes/ChatInThreadMode.js';

    const composerContext = useComposerContext();

    const DIM_CLASS = 'old-ui-mode-dim';
    const HIGHLIGHT_CLASS = 'old-ui-mode-highlight';
    const HIDE_CLASS = 'old-ui-mode-hide';

    function scrollElementIntoViewIfNeeded(elOrMessageId: string | HTMLElement) {
        let el: HTMLElement;
        if (typeof elOrMessageId === 'string') {
            el = document.getElementById(elOrMessageId)!;
        } else {
            el = elOrMessageId;
        }
        if (!el) {
            return;
        }
        const scrollContainer = document.querySelector('.chatlog .scroll-container');
        if (scrollContainer instanceof HTMLElement) {
            const containerRect = scrollContainer.getBoundingClientRect();
            const elRect = el.getBoundingClientRect();
            if (elRect.top < containerRect.top || elRect.bottom > containerRect.bottom) {
                // Element is out of view, scroll to it
                const yOffset = 100; // Adjust this value as needed
                const y = el.getBoundingClientRect().top + scrollContainer.scrollTop - yOffset;
                scrollContainer.scrollTo({top: y});
            }
        }
    }

    function scrollToEnd() {
        const scrollContainer = document.querySelector('.chatlog .scroll-container');
        if (scrollContainer instanceof HTMLElement) {
            scrollContainer.scrollTo({top: scrollContainer.scrollHeight});
        }
    }

    function highlightMessageWithId(messageId: string, hideThreads?: boolean): () => void {
        const elementsToHide = document.querySelectorAll('.message,.date_span');
        elementsToHide.forEach(msg => msg.classList.add(HIDE_CLASS));
        const el = document.getElementById(messageId);
        let threadBranch: HTMLElement | null = null;
        if (el) {
            el.classList.add(HIGHLIGHT_CLASS);
            el.classList.remove(HIDE_CLASS);

            if (messageIdBelongsToThread(messageId)) {
                // messageIdPattern threadId.messageId e.g. 1234.132
                // Fetch all elements with those ids and remove the hide class, to make sure the whole thread is visible
                const threadId = messageId.split('.')[0];
                const threadMessages = document.querySelectorAll(`[id^="${threadId}."]`);
                threadMessages.forEach(msg => msg.classList.remove(HIDE_CLASS));
                hideThreads = false;
            } else if (!hideThreads) {
                const childMessages = el.querySelectorAll('.message');
                childMessages.forEach(msg => msg.classList.remove(HIDE_CLASS));
            }

            if (hideThreads || hideThreads === undefined) {
                threadBranch = el.querySelector('.thread.branch') as HTMLElement | null;
                if (threadBranch) {
                    threadBranch.classList.add(HIDE_CLASS);
                }
            }
        }
        return () => {
            elementsToHide.forEach(msg => msg.classList.remove(HIDE_CLASS));
            el?.classList.remove(HIGHLIGHT_CLASS);
            scrollElementIntoViewIfNeeded(messageId);
            threadBranch?.classList.remove(HIDE_CLASS);
        };
    }

    function disableElementInteractions(messageId: string): () => void {
        const el = document.getElementById(messageId);
        if (el) {
            const controls = el.querySelector('.message-controls .buttons') as HTMLDivElement | null;
            if (controls) {
                controls.classList.add(DIM_CLASS);
                controls.style.pointerEvents = 'none';
            }
            return () => {
                if (controls) {
                    controls.classList.remove(DIM_CLASS);
                    controls.style.removeProperty('pointer-events');
                }
            };
        }
        return () => {
        };
    }

    function messageIdBelongsToThread(messageId: string): boolean {
        return !messageId.endsWith('000');
    }

    function highlightAndDisableMessageWithId(messageId: string, hideThreads?: boolean): () => void {
        if (hideThreads === undefined && messageIdBelongsToThread(messageId)) {
            hideThreads = false;
        }
        const rollbackHighlight = highlightMessageWithId(messageId, hideThreads);
        const rollbackInteractions = disableElementInteractions(messageId);
        scrollElementIntoViewIfNeeded(messageId);
        return () => {
            rollbackHighlight();
            rollbackInteractions();
            document.body.setAttribute('data-no-auto-scroll', 'true');
            scrollElementIntoViewIfNeeded(messageId);
            setTimeout(() => {
                document.body.removeAttribute('data-no-auto-scroll');
            }, 50);
        };
    }

    function styleElementForEditMode(modeState: ChatEditModeState): () => void {
        return highlightAndDisableMessageWithId(modeState.messageId);
    }

    function styleElementForThreadMode(modeState: ChatThreadModeState): () => void {
        const threadContainers = document.querySelectorAll('.thread.branch.visible');
        const container = Array.from(threadContainers)
            .find(c => `${c.id}` === `${modeState.threadId}`) as HTMLElement | undefined;

        if (container) {
            container.classList.add('thread-editing');
        }

        let parentMessageId: string | null = null;
        let rollbackHighlightAndDisable: (() => void) | null = null;
        const closestMessage = container?.closest('.message') as HTMLElement | null;
        if (closestMessage && closestMessage.id) {
            parentMessageId = closestMessage.id;
            rollbackHighlightAndDisable = highlightAndDisableMessageWithId(parentMessageId, false);
        }

        scrollToEnd();

        return () => {
            if (container) {
                container.classList.remove('thread-editing');
            }
            if (rollbackHighlightAndDisable) {
                rollbackHighlightAndDisable();
            }
            scrollToEnd();
        };
    }


    $effect(() => {
        if (composerContext.mode.isEdit) {
            return styleElementForEditMode(composerContext.mode.getState('edit'));
        }
        if (composerContext.mode.isThread) {
            return styleElementForThreadMode(composerContext.mode.getState('thread'));
        }
    });
</script>
<style>
    :global(.old-ui-mode-highlight) {
        outline: 2px solid var(--color-info);
        box-shadow: 0 0 10px var(--color-info);
    }

    :global(.old-ui-mode-hide) {
        display: none;
    }

    :global(.old-ui-mode-dim) {
        opacity: 0.2;
        pointer-events: none;
        cursor: not-allowed;
    }
</style>
