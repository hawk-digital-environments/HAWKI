<!--
  @component The composer's main message input. Renders an auto-growing
  `Textarea` and handles the Enter-to-send / Shift+Enter-newline /
  Escape-to-exit-mode keyboard shortcuts. The assistants tagged in the message
  are shown as `MentionChips` up in the composer's top row, next to the model
  picker. Hides itself entirely when the current mode disables the
  `'input'` feature (e.g. while a non-abortable send is in flight).

  `ComposerContext.message` stays the source of truth and keeps carrying the
  `@handle` tokens (everything downstream — `containsAiHandle`, the guard, the
  send pipeline — reads them from there). The textarea only ever shows the text
  *around* them: handles are stripped for display and re-attached on every edit,
  so a tagged assistant is a chip rather than editable text.

  It also owns the two caret-anchored autocompletes, which share one flow: typing
  `@` at a word boundary (room chats only) opens `AssistantMentionPopup` to tag an
  assistant, typing `/` opens `ToolMentionPopup` to switch a tool on or off without
  reaching for the tool menu. Further typing filters the open list by name, and
  ArrowUp/ArrowDown/Enter/Tab/Escape drive it — the textarea keeps focus throughout,
  so the popup is a `listbox` this component points at via `aria-activedescendant`.
  Selecting drops the typed `@query`/`/query` again: an assistant becomes a handle on
  the message, a tool is toggled in `composerContext.tools`.

  Reads/writes `ComposerContext` directly — there is no props-based way to
  set the message text; bind to `composerContext.message` from a parent if
  you need to observe or set it externally.

  @example
  ```svelte
      // inside a component nested under createComposerContext()
  <ComposerTextarea bind:ref={textareaEl} onSend={handleSend}/>
  ```
-->
<script lang="ts">

    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {reportAttachmentIssues} from '$plugins/core/modules/chat/components/utils/attachmentIssues.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import AssistantMentionPopup, {assistantMentionOptionId} from '$plugins/core/modules/chat/components/composer/AssistantMentionPopup.svelte';
    import ToolMentionPopup, {toolMentionOptionId, type ToolMentionEntry} from '$plugins/core/modules/chat/components/composer/ToolMentionPopup.svelte';
    import {getTextareaCaretRect, type CaretRect} from '$plugins/core/modules/chat/components/composer/utils/textareaCaret.js';
    import type {AiAssistantHandle} from '$plugins/core/stores/AiHandleStore.svelte.js';

    const composerContext = useComposerContext();
    const aiHandleStore = useStore('ai-handle');
    const aiToolStore = useStore('ai-tools');
    const translator = useTranslator();
    const {__} = translator;

    interface Props {
        /** Called when the user presses Enter (without Shift) to submit the message.
         *  The textarea itself does not clear or send anything — the parent (typically
         *  `ChatComposer`) owns the actual send flow via `ComposerContext.send()`. */
        onSend?: () => void;
        /** Bindable reference to the underlying `<textarea>` element, e.g. so a parent
         *  can pass it to `ComposerFocusWrap` for click-to-focus behaviour. */
        ref?: HTMLTextAreaElement | null;
    }

    let {
        onSend,
        ref = $bindable(null)
    }: Props = $props();

    // ── message text vs. displayed text ──────────────────────────────────
    // The textarea shows the message without its `@handle` tokens (those are chips), so
    // every read strips them and every write puts them back. Deliberately not built on
    // `messageWithoutHandles`, which trims: trimming would swallow a space the moment the
    // user typed it and bounce the caret.

    /** The message minus its handle tokens (and the single space that follows each). */
    function stripHandles(message: string): string {
        let text = '';
        let cursor = 0;
        for (const match of aiHandleStore.getHandleMatchesIn(message)) {
            text += message.slice(cursor, match.start);
            cursor = match.end;
            // Swallow the separator the handle brought with it, so removing "@hawki " from
            // "@hawki hi" leaves "hi" rather than " hi".
            if (message[cursor] === ' ') {
                cursor += 1;
            }
        }
        return text + message.slice(cursor);
    }

    /** Re-attaches the current handles in front of `body` to form the message. */
    function composeMessage(handles: string[], body: string): string {
        if (handles.length === 0) {
            return body;
        }
        return `${handles.join(' ')} ${body}`;
    }

    const body = $derived(stripHandles(composerContext.message));

    function setBody(value: string) {
        composerContext.message = composeMessage(composerContext.handlesInMessage, value);
    }

    const textareaPlaceholder = $derived.by(() => {
        if (composerContext.type === 'aiConv') {
            return __('chat.composer.textareaPlaceholder', {model: composerContext.model?.current.label ?? ''});
        } else {
            return __('chat.composer.textareaPlaceholderRoom');
        }
    });

    const textareaLabel = $derived(
        composerContext.type === 'aiConv'
            ? __('chat.composer.textareaLabel')
            : __('chat.composer.textareaLabelRoom')
    );

    // ── `@` / `/` autocomplete flow ───────────────────────────────────────
    // The trigger being typed, or null when the caret isn't inside one. Derived
    // from the text around the caret on every input/selection change rather than
    // tracked as a state machine, so editing anywhere (paste, arrow keys, undo)
    // stays consistent.
    type MentionTrigger = '@' | '/';
    let mention = $state<{ trigger: MentionTrigger; start: number; query: string } | null>(null);
    let caret = $state<CaretRect | null>(null);
    let activeIndex = $state(0);
    // Offset of a mention the user dismissed with Escape; keeps the popup closed
    // while the caret stays in that same mention.
    let dismissedStart = $state<number | null>(null);
    // Set between applying a mention and the textarea value catching up, so the events
    // that fire in between don't re-detect the token we just consumed.
    let awaitingFlush = false;

    // Study assistants can be tagged anywhere; HAWKI itself only in a room, where it is one
    // participant among many — in an AI conversation every message already goes to it.
    const taggableAssistants = $derived(
        composerContext.type === 'room'
            ? aiHandleStore.assistants
            : aiHandleStore.assistants.filter(assistant => assistant.handle !== aiHandleStore.hawkiHandle)
    );
    const mentionsEnabled = $derived(taggableAssistants.length > 0);
    // The `/` menu is the tool menu by another route, so it follows the same guard — and,
    // like the tool button, stays available in a room chat that tags nobody yet, so tools
    // can be picked before choosing who they are for.
    const toolsEnabled = $derived(!composerContext.guard.disablesFeature('tools'));

    const matchingAssistants = $derived.by(() => {
        if (mention?.trigger !== '@') {
            return [];
        }
        const query = mention.query.toLowerCase();
        return taggableAssistants.filter(assistant => {
            if (query === '') {
                return true;
            }
            return assistant.handle.slice(1).toLowerCase().startsWith(query)
                || __(assistant.labelKey).toLowerCase().includes(query);
        });
    });

    // Only tools the current model can actually run are offered: unlike the tool menu,
    // which also lists the unusable ones with a warning so they can be inspected, this
    // list exists to turn something on in a single keystroke.
    const matchingTools = $derived.by(() => {
        if (mention?.trigger !== '/') {
            return [];
        }
        const query = mention.query.toLowerCase();
        return aiToolStore.tools
            .filter(tool => tool.status !== 'offline' && tool.isAvailableFor(composerContext.model.current))
            .filter(tool => query === '' || tool.displayName.toLowerCase().includes(query))
            .sort((a, b) => a.displayName.localeCompare(b.displayName))
            .map((tool): ToolMentionEntry => ({tool, active: composerContext.tools.isActive(tool)}));
    });

    const itemCount = $derived(mention?.trigger === '/' ? matchingTools.length : matchingAssistants.length);
    const popupOpen = $derived(!!mention && !!caret && itemCount > 0);

    // Typing narrows the list under the highlight, and the query changing is not a new
    // mention, so `syncMention` leaves `activeIndex` alone. Without this the highlight can
    // sit past the end of the shortened list and Enter would apply nothing at all.
    $effect(() => {
        if (activeIndex >= itemCount) {
            activeIndex = 0;
        }
    });

    /** Reads the trigger the caret currently sits in, or null when there is none.
     *  A trigger starts at a word boundary, so an `@` inside a word (e.g. an e-mail
     *  address) or a `/` inside a path or URL never opens a popup. */
    function detectMention(textarea: HTMLTextAreaElement): { trigger: MentionTrigger; start: number; query: string } | null {
        const caretOffset = textarea.selectionStart ?? 0;
        // Only a collapsed caret is a mention; a selection means the user is doing something else.
        if ((textarea.selectionEnd ?? 0) !== caretOffset) {
            return null;
        }
        const match = /(?:^|\s)([@/])([a-zA-Z0-9_-]*)$/.exec(textarea.value.slice(0, caretOffset));
        if (!match) {
            return null;
        }
        const trigger = match[1] as MentionTrigger;
        if (trigger === '@' ? !mentionsEnabled : !toolsEnabled) {
            return null;
        }
        return {trigger, start: caretOffset - match[2].length - 1, query: match[2]};
    }

    function syncMention(textarea: HTMLTextAreaElement) {
        // The keyup that follows the Enter which just applied a mention still sees the
        // pre-flush value ("/websearch"), so without this the popup would immediately
        // reopen on a token the user has already resolved.
        if (awaitingFlush) {
            return;
        }

        const next = detectMention(textarea);
        // Leaving the dismissed mention re-arms the popup.
        if (dismissedStart !== null && next?.start !== dismissedStart) {
            dismissedStart = null;
        }

        if (!next || next.start === dismissedStart) {
            mention = null;
            caret = null;
            return;
        }

        // Anchor to the trigger character rather than the moving caret, so the popup stays
        // put while typing.
        caret = getTextareaCaretRect(textarea, next.start);
        if (next.start !== mention?.start || next.trigger !== mention?.trigger) {
            activeIndex = 0;
        }
        mention = next;
    }

    /** Drops the typed trigger and its query from the text and puts the caret back where
     *  the trigger was, returning that offset. Both popups start here — what they add is
     *  what happens *instead* of the removed text. */
    function consumeMention(): number | null {
        const textarea = ref;
        if (!textarea || !mention) {
            return null;
        }

        const {start} = mention;
        const end = textarea.selectionStart ?? start;
        setBody(textarea.value.slice(0, start) + textarea.value.slice(end));
        mention = null;
        caret = null;
        dismissedStart = null;
        awaitingFlush = true;

        // The value lands on the element only after the binding flushes, so restore the
        // caret where the mention was on the next frame.
        requestAnimationFrame(() => {
            awaitingFlush = false;
            textarea.focus();
            textarea.setSelectionRange(start, start);
        });
        return start;
    }

    /** Turns the typed `@query` into a chip: the query is dropped from the text and the
     *  assistant's handle is added to the message — or removed again if it was already
     *  tagged, so picking the checked row untags it, the same way `/` toggles a tool. */
    function insertMention(assistant: AiAssistantHandle) {
        const wasTagged = composerContext.handlesInMessage.includes(assistant.handle);
        if (consumeMention() === null) {
            return;
        }
        if (wasTagged) {
            composerContext.removeHandleFromMessage(assistant.handle);
        } else {
            composerContext.addHandleToMessage(assistant.handle);
        }
    }

    /** Turns the typed `/query` into a tool switch: the query is dropped from the text and
     *  the tool is enabled — or disabled again, so the same command undoes itself. */
    function toggleTool(entry: ToolMentionEntry) {
        const wasActive = composerContext.tools.isActive(entry.tool);
        if (consumeMention() === null) {
            return;
        }
        if (wasActive) {
            composerContext.tools.disable(entry.tool);
        } else {
            composerContext.tools.enable(entry.tool);
        }
    }

    /** Applies the highlighted entry of whichever popup is open. */
    function selectActive() {
        const entry = mention?.trigger === '/' ? matchingTools[activeIndex] : matchingAssistants[activeIndex];
        if (!entry) {
            return;
        }
        if ('tool' in entry) {
            toggleTool(entry);
        } else {
            insertMention(entry);
        }
    }

    /** Closes the popup and remembers the mention, so it stays closed while the caret
     *  remains inside it (Escape). */
    function dismissMention() {
        dismissedStart = mention?.start ?? null;
        mention = null;
        caret = null;
    }

    /** Closes the popup without dismissing the mention — it reopens when the textarea
     *  is focused inside the same mention again (blur). */
    function hideMention() {
        mention = null;
        caret = null;
    }

    function handleInput(e: Event) {
        syncMention(e.target as HTMLTextAreaElement);
    }

    /** Keeps the popup in sync when the caret moves without editing (clicks, arrow keys). */
    function handleSelectionChange(e: Event) {
        syncMention(e.target as HTMLTextAreaElement);
    }

    /** Popup-first key handling; returns true when the key was consumed by the popup. */
    function handleMentionKeyDown(e: KeyboardEvent): boolean {
        if (!popupOpen) {
            return false;
        }

        switch (e.key) {
            case 'ArrowDown':
                activeIndex = (activeIndex + 1) % itemCount;
                return true;
            case 'ArrowUp':
                activeIndex = (activeIndex - 1 + itemCount) % itemCount;
                return true;
            case 'Enter':
            case 'Tab':
                if (e.shiftKey) {
                    return false;
                }
                selectActive();
                return true;
            case 'Escape':
                dismissMention();
                return true;
            default:
                return false;
        }
    }

    function handleKeyDown(e: KeyboardEvent) {
        // The popup owns Enter/Tab/arrows/Escape while it is open, so Enter picks an
        // assistant or tool instead of sending the message.
        if (handleMentionKeyDown(e)) {
            e.preventDefault();
            e.stopPropagation();
            return;
        }

        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            onSend?.();
        }
        if (e.key === 'Escape' && !composerContext.mode.isDefault) {
            e.preventDefault();
            composerContext.mode.exit();
        }
    }

    let oldMessage = composerContext.message;
    $effect(() => {
        if (ref && composerContext.message !== oldMessage) {
            ref.style.height = 'auto';
            ref.style.height = Math.min(ref.scrollHeight, 250) + 'px';
        }
    });

    const toastContext = useToastContext();

    function handlePaste(e: ClipboardEvent) {
        const clipboard = e.clipboardData;
        if (!clipboard || !Array.from(clipboard.types).includes('Files')) return;

        e.preventDefault();
        reportAttachmentIssues(translator, toastContext, composerContext.attachments.add(clipboard.files));
    }

    const activeOptionId = $derived.by(() => {
        if (!popupOpen) {
            return undefined;
        }
        return mention?.trigger === '/'
            ? toolMentionOptionId(activeIndex)
            : assistantMentionOptionId(activeIndex);
    });
</script>
{#if !composerContext.guard.disablesFeature('input', false)}
    <div
        class={'chat-textarea-wrapper'}
        transition:growTransition
    >
        <Textarea
            bind:ref={ref}
            bind:value={() => body, setBody}
            disabled={composerContext.sendStatus?.sending}
            onkeydown={handleKeyDown}
            onpaste={handlePaste}

            oninput={handleInput}
            onclick={handleSelectionChange}
            onkeyup={handleSelectionChange}
            onblur={hideMention}
            role={popupOpen ? 'combobox' : undefined}
            aria-expanded={popupOpen}
            aria-autocomplete="list"
            aria-activedescendant={activeOptionId}
            class="chat-textarea"
            rows={1}
            ariaLabel={textareaLabel}
            placeholder={textareaPlaceholder}
        />
    </div>
{/if}

{#if popupOpen && caret}
    {#if mention?.trigger === '/'}
        <ToolMentionPopup
            entries={matchingTools}
            caret={caret}
            activeIndex={activeIndex}
            onActivate={(index) => (activeIndex = index)}
            onSelect={toggleTool}/>
    {:else}
        <AssistantMentionPopup
            assistants={matchingAssistants}
            activeHandle={composerContext.handlesInMessage[0] ?? null}
            caret={caret}
            activeIndex={activeIndex}
            onActivate={(index) => (activeIndex = index)}
            onSelect={insertMention}/>
    {/if}
{/if}

<style>
    .chat-textarea-wrapper {
        display: flex;
        align-items: flex-end;
    }

    /* ── Textarea ─────────────────────────────────────────────────────── */
    :global(.chat-textarea.chat-textarea) {
        width: 100%;
        min-height: 0.8lh;
        height: auto;
        resize: none;
        background: transparent;
        border: none;
        outline: none;
        padding-block: calc(var(--space-1) * 1);
        line-height: 1.25rem;
        box-shadow: none;

        &:focus,
        &:focus-visible {
            outline: none;
            border: none;
            box-shadow: none;
        }
    }
</style>
