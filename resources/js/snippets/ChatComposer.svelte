<!--
  @component Primary chat input panel. Combines message textarea, model selector,
  tool/file controls, system-prompt editing, and a conflict resolver for
    capability mismatches.
-->
<script lang="ts">
    import ModelConflictPicker from '$lib/components/chat/composer/ModelConflictPicker.svelte';
    import ToolChips from '$lib/components/chat/composer/ToolChips.svelte';
    import FileChips from '$lib/components/chat/composer/FileChips.svelte';
    import ModelPicker from '$lib/components/chat/composer/ModelPicker.svelte';
    import {type ComposerContextType, createComposerContext} from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';
    import FileDragAndDrop from '$lib/components/chat/composer/FileDragAndDrop.svelte';
    import FilePicker from '$lib/components/chat/composer/FilePicker.svelte';
    import ToolMenu from '$lib/components/chat/composer/ToolMenu.svelte';
    import SettingsMenu from '$lib/components/chat/composer/SettingsMenu.svelte';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import ModePanel from '$lib/components/chat/composer/ModePanel.svelte';
    import OldUiStyling from '$lib/components/chat/composer/OldUiStyling.svelte';
    import ComposerTextarea from '$lib/components/chat/composer/ComposerTextarea.svelte';
    import ComposerActionButtons from '$lib/components/chat/composer/ComposerActionButtons.svelte';
    import ComposerFocusWrap from '$lib/components/chat/composer/utils/ComposerFocusWrap.svelte';
    import ComposerBorderBeam from '$lib/components/chat/composer/ComposerBorderBeam.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';

    interface Props {
        context: ComposerContextType;
    }

    const {context: contextType = 'aiConv'}: Props = $props();

    const toastContext = useToastContext();
    // This is a bit of a hack to work around the "state_referenced_locally" warning thrown by svelte.
    const chatContext = createComposerContext((() => contextType)(), toastContext);

    let textareaEl = $state(null as HTMLTextAreaElement | null);
    let buttonEl = $state(null as HTMLButtonElement | null);

    // Shared so the tool-chip overflow badge can open the picker.
    let toolPickerOpen = $state(false);

    const hasFiles = $derived(chatContext.attachments.list.length > 0);

    async function handleSend() {
        const status = chatContext.send();

        // If we can currently not send, the send function returns null.
        // This occurs when the "context.guard.canSend" check fails, which can be due to various reasons.
        if (status === null) {
            return;
        }

        // When this promise resolves, it means the send action has been accepted and the response object is available.
        // It does NOT mean that the response body has been fully received yet or that there were no issues with the
        // send action. Any issues (like validation errors) will be included in the "status" object, which is why we check
        // that first before proceeding to handle the response.
        const response = await status.response;

        // If the send action failed (e.g. due to validation/network errors), show the issues in toasts
        // but keep the current context as is (e.g. don't clear the input or switch modes), since the user might want to fix the issues and try sending again.
        if (status.failed) {
            status.sendIssues.forEach(issue => {
                toastContext.error(issue);
            });
            status.fileIssues.forEach(([file, issue]) => {
                toastContext.error(`${file.name}: ${issue}`);
            });
            // Some files may already have been uploaded and have a uuid assigned.
            // in this case we inherit those uuids in the current context so
            // when the user sends the request again, we don't need to re-upload those files but can reuse the already assigned uuids.
            status.fileUuids.forEach(([file, uuid]) => {
                chatContext.attachments.assignUuid(file, uuid);
            });
            return;
        }

        // There might be issues that occur while we wait for the response body (e.g. a network error).
        // For those cases we listen for errors on the response object itself and show a toast if that happens.
        response.onError(error => {
            console.error('Error while receiving response body:', error);
            toastContext.error(error);
        });

        // Clear the input immediately to give back control to the user.
        // However, this will still keep the current mode, since we can only switch modes after the response body is received.
        chatContext.clear();

        // Refocus the input after sending, since the user might want to immediately start typing the next message.
        chatContext.focusInput();

        // Wait until the response body is fully received before allowing another message to be sent.
        // If we don't need to wait for a streamed response, this will already be resolved and thus not cause any delay.
        await response.body;

        // If the current mode has "exitAfterSend" set to true, exit it after sending the message.
        if (chatContext.mode.exitAfterSend) {
            chatContext.mode.exit();
        }

        if (textareaEl) textareaEl.style.height = 'auto';
    }
</script>
{#if chatContext.hasWriteAccess}
    <div class="chat-composer-wrapper">
        <OldUiStyling/>
        <FileDragAndDrop>
            {#snippet children({dragOverlay, isDragging})}
                <ComposerBorderBeam>
                    <ComposerFocusWrap textareaEl={textareaEl} buttonEl={buttonEl} class="chat-composer-card">
                        {@render dragOverlay()}
                        <div class="chat-composer-body" class:chat-composer-body--hidden={isDragging}>
                            <ModePanel/>
                            <!-- Upper row: model selector (left) + settings (right) -->
                            <div class="chat-composer-top-row">
                                {#if chatContext.guard.showsAiUiElements}
                                    <!-- Left: model controls -->
                                    <div class="chat-composer-left" transition:growTransition>
                                        <ModelPicker/>
                                    </div>

                                    <!-- Right: settings -->
                                    <div class="chat-composer-right" transition:growTransition>
                                        <SettingsMenu/>
                                    </div>
                                {/if}
                            </div>

                            <!-- Separator -->
                            <div class="chat-composer-sep"></div>

                            <ComposerTextarea bind:ref={textareaEl} onSend={handleSend}/>
                            <ModelConflictPicker/>

                            <!-- File chips -->
                            <div class={{
                                'chat-section-shell': true,
                                'chat-section-shell--open': hasFiles
                            }}>
                                <div class="chat-section-mask">
                                    <div class="chat-chips">
                                        <FileChips/>
                                    </div>
                                </div>
                            </div>

                            <!-- Lower row: attach/tool controls (left) + send button (right) -->
                            <div class="chat-composer-bottom-row">
                                <div class="chat-bottom-left">
                                    <FilePicker/>
                                    <ToolMenu bind:open={toolPickerOpen}/>
                                    <div class="chat-tool-chip-lane">
                                        <ToolChips onShowMore={() => (toolPickerOpen = true)}/>
                                    </div>
                                </div>
                                <div class="chat-bottom-right">
                                    <ComposerActionButtons
                                        onSend={handleSend}
                                        bind:buttonRef={buttonEl}/>
                                </div>
                            </div>
                        </div>
                    </ComposerFocusWrap>
                </ComposerBorderBeam>
            {/snippet}
        </FileDragAndDrop>
    </div>
{/if}

<style>
    /* ── Outer wrapper ────────────────────────────────────────────────── */

    .chat-composer-wrapper {
        margin-inline: auto;
        width: 100%;
        max-width: 48rem;
    }

    /* ── Card ─────────────────────────────────────────────────────────── */

    :global(.chat-composer-card) {
        --card-bg: color-mix(in oklch, var(--color-surface-raised) 60%, transparent);

        position: relative;
        border-radius: var(--corner-lg);
        border: var(--border);
        background-color: var(--card-bg);
        backdrop-filter: blur(8px);
        overflow: hidden;
        cursor: text;
        transform-origin: bottom;
        transition: border-color var(--duration-fast, 150ms) var(--easing-default);
    }

    :global(.chat-composer-card--error) {
        border-color: color-mix(in oklch, var(--color-error) 50%, transparent);
    }

    /* Keep the body in flow so the card preserves its height, but hide it
           behind the drop hint. */
    .chat-composer-body--hidden {
        visibility: hidden;
    }

    /* ── Top row ──────────────────────────────────────────────────────── */

    .chat-composer-top-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2);
        padding-inline: var(--space-2, calc(0.25rem * 2));
        padding-top: var(--space-2, calc(0.25rem * 2));
        padding-bottom: 4px;
    }

    .chat-composer-left {
        display: flex;
        align-items: center;
        gap: calc(0.25rem * 1.5);
        min-width: 0;
    }

    /* Let a long model name truncate instead of pushing the settings button away. */
    .chat-composer-left :global(.chat-model-trigger) {
        min-width: 0;
    }

    .chat-composer-left :global(.chat-model-trigger > span) {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .chat-composer-right {
        display: flex;
        align-items: center;
        gap: calc(0.25rem * 1.5);
        flex-shrink: 0;
    }

    /* ── Horizontal separator ─────────────────────────────────────────── */

    .chat-composer-sep {
        margin-inline: var(--space-4, calc(0.25rem * 4));
        border-top: none;
    }

    .chat-section-shell {
        display: grid;
        grid-template-rows: 0fr;
        transition: grid-template-rows var(--duration-fast, 300ms) var(--easing-spring);
    }

    .chat-section-shell--open {
        grid-template-rows: 1fr;
    }

    .chat-section-mask {
        min-height: 0;
        overflow: hidden;
    }

    /* ── Chips row ────────────────────────────────────────────────────── */

    .chat-chips {
        display: flex;
        flex-wrap: wrap;
        gap: calc(0.25rem * 1.5);
        padding-inline: var(--space-2, calc(0.25rem * 2));
        padding-bottom: calc(0.25rem * 1.5);
        animation: composer-section-slide-up var(--duration-fast, 300ms) var(--easing-spring) both;
    }

    /* ── Bottom row ───────────────────────────────────────────────────── */

    .chat-composer-bottom-row {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        padding-inline: var(--space-2, calc(0.25rem * 2));
        padding-top: 4px;
        padding-bottom: var(--space-2, calc(0.25rem * 2));
    }

    .chat-bottom-left {
        display: flex;
        align-items: flex-end;
        gap: calc(0.25rem * 1.5);
        /* Claim the row space (minus the send button) so the chip lane has a
           stable width to fill and measure against. */
        flex: 1 1 0;
        min-width: 0;
    }

    .chat-bottom-right {
        display: flex;
        align-items: center;
        gap: calc(0.25rem * 1.5);
        flex-shrink: 0;
    }

    .chat-tool-chip-lane {
        display: flex;
        align-items: flex-end;
        /* Take the remaining row space so the chip row has a stable width to
           measure against (independent of how many chips are shown). */
        flex: 1 1 0;
        min-width: 0;
        min-height: 1.5rem;
        overflow: hidden;
    }

    /* ── Send button helper ─────────────────────────────────────────── */
    @keyframes composer-section-slide-up {
        from {
            opacity: 0;
            clip-path: inset(100% 0 0 0);
            transform: translateY(0.5rem);
        }

        to {
            opacity: 1;
            clip-path: inset(0 0 0 0);
            transform: translateY(0);
        }
    }

</style>
