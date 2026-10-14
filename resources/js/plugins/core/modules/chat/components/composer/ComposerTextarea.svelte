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

  Typing `@` or `/` at a word boundary is the keyboard route into the composer's menus:
  the trigger character is consumed and `onTriggerMenu` asks the parent (typically
  `ChatComposer`) to open `AssistantMenu` or `ToolMenu`, whose search field takes over
  from there — the caret popup this used to open in place was folded into those menus,
  so there is one surface per choice.

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

    const composerContext = useComposerContext();
    const aiHandleStore = useStore('ai-handle');
    const translator = useTranslator();
    const {__} = translator;

    interface Props {
        /** Called when the user presses Enter (without Shift) to submit the message.
         *  The textarea itself does not clear or send anything — the parent (typically
         *  `ChatComposer`) owns the actual send flow via `ComposerContext.send()`. */
        onSend?: () => void;
        /** Called when the user types `@` or `/` at a word boundary, after the trigger
         *  character has been removed from the text — the parent opens the matching
         *  menu (`AssistantMenu` / `ToolMenu`) in response. */
        onTriggerMenu?: (trigger: '@' | '/') => void;
        /** Bindable reference to the underlying `<textarea>` element, e.g. so a parent
         *  can pass it to `ComposerFocusWrap` for click-to-focus behaviour. */
        ref?: HTMLTextAreaElement | null;
    }

    let {
        onSend,
        onTriggerMenu,
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

    // ── `@` / `/` menu triggers ─────────────────────────────────────────
    // Typing `@` or `/` at a word boundary opens the corresponding composer menu
    // (`AssistantMenu` / `ToolMenu`): the trigger character is consumed so it never
    // becomes part of the message, and the menu's search field takes over. The menus'
    // open state lives in the parent (`ChatComposer`); this component only detects the
    // trigger and reports it upward.

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

    /** Whether the just-inserted character at the caret is a menu trigger. A trigger
     *  only counts at a word boundary, so an `@` inside a word (e.g. an e-mail
     *  address) or a `/` inside a path or URL never opens a menu. */
    function detectMenuTrigger(textarea: HTMLTextAreaElement, data: string | null): '@' | '/' | null {
        if (data !== '@' && data !== '/') {
            return null;
        }
        const caretOffset = textarea.selectionStart ?? 0;
        // The char before the trigger (if any) must be whitespace — the trigger itself,
        // just inserted, is the one before the caret.
        const before = caretOffset >= 2 ? textarea.value[caretOffset - 2] : undefined;
        if (before !== undefined && !/\s/.test(before)) {
            return null;
        }
        if (data === '@' ? !mentionsEnabled : !toolsEnabled) {
            return null;
        }
        return data;
    }

    /** Drops the just-typed trigger character — it never becomes part of the message —
     *  and hands the choice to the corresponding menu via `onTriggerMenu`. */
    function openMenuFor(trigger: '@' | '/'): void {
        const textarea = ref;
        if (!textarea) {
            return;
        }
        const caretOffset = textarea.selectionStart ?? 0;
        setBody(textarea.value.slice(0, caretOffset - 1) + textarea.value.slice(caretOffset));
        onTriggerMenu?.(trigger);
        // The value lands on the element only after the binding flushes, so put the
        // caret where the trigger was on the next frame — it is where focus returns
        // when the menu closes again.
        requestAnimationFrame(() => {
            textarea.setSelectionRange(caretOffset - 1, caretOffset - 1);
        });
    }

    function handleInput(e: Event) {
        // Only freshly inserted characters open a menu — never a deletion, a caret
        // move, or text restored by the binding flush (those carry no `data`).
        const textarea = e.target as HTMLTextAreaElement;
        const trigger = detectMenuTrigger(textarea, e instanceof InputEvent ? e.data : null);
        if (trigger) {
            openMenuFor(trigger);
        }
    }

    function handleKeyDown(e: KeyboardEvent) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            onSend?.();
        }
        if (e.key === 'Escape' && !composerContext.mode.isDefault) {
            e.preventDefault();
            composerContext.mode.exit();
        }
        // The paste event carries no modifier state, so remember Ctrl/Cmd+Shift+V
        // here; any other keystroke clears it again.
        forceInlinePaste = e.shiftKey && (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'v';
    }

    let oldMessage = composerContext.message;
    $effect(() => {
        if (ref && composerContext.message !== oldMessage) {
            ref.style.height = 'auto';
            ref.style.height = Math.min(ref.scrollHeight, 250) + 'px';
        }
    });

    const toastContext = useToastContext();

    /** Pasted plain text longer than this (in characters) is attached as a `.txt` file instead of inserted inline. */
    const PASTE_AS_FILE_THRESHOLD = 1000;

    /** True while the pending paste was triggered via Ctrl/Cmd+Shift+V, which forces inline text insertion. */
    let forceInlinePaste = false;

    function handlePaste(e: ClipboardEvent) {
        const pasteInline = forceInlinePaste;
        forceInlinePaste = false;

        const clipboard = e.clipboardData;
        if (!clipboard) return;

        if (Array.from(clipboard.types).includes('Files')) {
            e.preventDefault();
            reportAttachmentIssues(translator, toastContext, composerContext.attachments.add(clipboard.files));
            return;
        }

        if (pasteInline) return;

        const text = clipboard.getData('text/plain');
        if (text.length <= PASTE_AS_FILE_THRESHOLD || composerContext.guard.disablesFeature('attachments')) return;

        const file = new File([text], __('chat.composer.pastedTextFileName') + '.txt', {type: 'text/plain'});
        const result = composerContext.attachments.add(file);
        // If the file can't be attached (e.g. text/plain not allowed), fall back to inserting the text inline.
        if (result !== true) return;

        e.preventDefault();
    }
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
            class="chat-textarea"
            rows={1}
            ariaLabel={textareaLabel}
            placeholder={textareaPlaceholder}
        />
    </div>
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
