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

  It also drives the two commands, which share one flow: typing `@` at a word
  boundary opens the composer's `AssistantMenu` to tag an assistant, typing `/` opens
  its `ToolMenu` to switch a tool on or off — the same menus as the buttons, opened in
  command mode at the typed trigger (see `utils/commandMenu.ts`). Further typing filters the open menu by
  name, and ArrowUp/ArrowDown/Enter/Tab/Escape drive it — the textarea keeps focus
  throughout. Picking a row drops the typed `@query`/`/query` again: an assistant
  becomes a handle on the message, a tool is toggled in `composerContext.tools`.

  Reads/writes `ComposerContext` directly — there is no props-based way to
  set the message text; bind to `composerContext.message` from a parent if
  you need to observe or set it externally.

  @example
  ```svelte
      // inside a component nested under createComposerContext()
  <ComposerTextarea
      bind:ref={textareaEl}
      bind:command
      bind:assistantMenuOpen
      bind:toolMenuOpen
      {assistantMenu}
      {toolMenu}
      onSend={handleSend}/>
  ```
-->
<script lang="ts">

    import {untrack} from 'svelte';
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import {growTransition} from '$lib/utils/transitions/growTransition';
    import {useComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import type {CommandMenuHandle, MenuCommand} from '$plugins/core/modules/chat/components/composer/utils/commandMenu.js';
    import {getTextareaCaretRect, type CaretRect} from '$plugins/core/modules/chat/components/composer/utils/textareaCaret.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {reportAttachmentIssues} from '$plugins/core/modules/chat/components/utils/attachmentIssues.js';

    const composerContext = useComposerContext();
    const aiHandleStore = useStore('ai-handle');
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
        /** The `@`/`/` command being typed, for the menu it opened to filter by. Written
         *  by the textarea; supports bind:command. */
        command?: (MenuCommand & { trigger: '@' | '/' }) | null;
        /** Whether the assistant menu is open; opened and closed by `@` commands. */
        assistantMenuOpen?: boolean;
        /** Whether the tool menu is open; opened and closed by `/` commands. */
        toolMenuOpen?: boolean;
        /** The assistant menu, driven while a `@` command has it open. */
        assistantMenu?: CommandMenuHandle | null;
        /** The tool menu, driven while a `/` command has it open. */
        toolMenu?: CommandMenuHandle | null;
    }

    let {
        onSend,
        ref = $bindable(null),
        command = $bindable(null),
        assistantMenuOpen = $bindable(false),
        toolMenuOpen = $bindable(false),
        assistantMenu = null,
        toolMenu = null
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

    // Screen-reader-only operating hint (Enter sends, Shift+Enter breaks the
    // line, Escape leaves edit/thread mode) linked via aria-describedby.
    const uid = $props.id();
    const hintId = `${uid}-hint`;
    const textareaHint = $derived(
        composerContext.mode.isDefault
            ? __('chat.composer.textareaHint')
            : `${__('chat.composer.textareaHint')} ${__('chat.composer.textareaHintEscape')}`
    );

    // ── `@` / `/` command flow ────────────────────────────────────────────
    // The trigger being typed, or null when the caret isn't inside one. Derived
    // from the text around the caret on every input/selection change rather than
    // tracked as a state machine, so editing anywhere (paste, arrow keys, undo)
    // stays consistent. While one is typed, the matching button menu is open in
    // command mode; `snapshot` records what that menu can change, so closing it can
    // tell a pick from a dismissal.
    type MentionTrigger = '@' | '/';
    let mention = $state<{ trigger: MentionTrigger; start: number; query: string; snapshot: string; anchor: CaretRect } | null>(null);
    // Offset of a mention the user dismissed with Escape; keeps the menu closed
    // while the caret stays in that same mention.
    let dismissedStart = $state<number | null>(null);
    // Set between applying a mention and the textarea value catching up, so the events
    // that fire in between don't re-detect the token we just consumed.
    let awaitingFlush = false;
    // Whether the current mention's menu has been seen open, so the moment before the
    // menu mounts isn't mistaken for it closing.
    let menuSeenOpen = false;

    // Study assistants can be tagged anywhere; HAWKI itself only in a room, where it is one
    // participant among many — in an AI conversation every message already goes to it.
    const mentionsEnabled = $derived(
        composerContext.type === 'room'
            ? aiHandleStore.assistants.length > 0
            : aiHandleStore.assistants.some(assistant => assistant.handle !== aiHandleStore.hawkiHandle)
    );
    // The `/` menu is the tool menu by another route, so it follows the same guard — and,
    // like the tool button, stays available in a room chat that tags nobody yet, so tools
    // can be picked before choosing who they are for. A tagged assistant brings its own
    // tools, so the menu goes away with the tool button once one is addressed.
    const toolsEnabled = $derived(
        !composerContext.guard.disablesFeature('tools')
        && !composerContext.handlesInMessage.some(handle => handle !== aiHandleStore.hawkiHandle)
    );

    const menuOpen = $derived(
        mention?.trigger === '@' ? assistantMenuOpen
            : mention?.trigger === '/' ? toolMenuOpen
                : false
    );
    const menu = $derived(mention?.trigger === '@' ? assistantMenu : mention?.trigger === '/' ? toolMenu : null);

    // What the menus filter by.
    $effect(() => {
        command = mention ? {trigger: mention.trigger, query: mention.query, anchor: mention.anchor} : null;
    });

    function setMenuOpen(trigger: MentionTrigger, value: boolean) {
        if (trigger === '@') {
            assistantMenuOpen = value;
        } else {
            toolMenuOpen = value;
        }
    }

    /** What a menu can change: the tagged assistant and the active tools. */
    function commandSnapshot(): string {
        return [
            ...composerContext.handlesInMessage,
            ...composerContext.tools.active.map(tool => tool.name).sort()
        ].join(' ');
    }

    // The menu closed while the mention is still typed — by picking a row, Escape or a click
    // outside. A pick drops the typed `@query`/`/query`; anything else leaves it as text and
    // keeps the menu closed while the caret stays in it.
    $effect(() => {
        if (!mention) {
            return;
        }
        if (menuOpen) {
            menuSeenOpen = true;
            return;
        }
        if (!menuSeenOpen) {
            return;
        }
        menuSeenOpen = false;
        untrack(() => {
            if (mention && commandSnapshot() !== mention.snapshot) {
                consumeMention();
            } else {
                dismissMention();
                // Unless the user clicked somewhere else that took focus.
                const focused = document.activeElement;
                if (!focused || focused === document.body || focused.closest('.dropdown-content')) {
                    ref?.focus();
                }
            }
        });
    });

    /** Reads the trigger the caret currently sits in, or null when there is none.
     *  A trigger starts at a word boundary, so an `@` inside a word (e.g. an e-mail
     *  address) or a `/` inside a path or URL never opens a menu. */
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
        // pre-flush value ("/websearch"), so without this the menu would immediately
        // reopen on a token the user has already resolved.
        if (awaitingFlush) {
            return;
        }

        const next = detectMention(textarea);
        // Leaving the dismissed mention re-arms the menu.
        if (dismissedStart !== null && next?.start !== dismissedStart) {
            dismissedStart = null;
        }

        if (!next || next.start === dismissedStart) {
            hideMention();
            return;
        }

        const isNewMention = next.start !== mention?.start || next.trigger !== mention?.trigger;
        if (isNewMention) {
            hideMention();
            // Anchored to the trigger character rather than the moving caret, so the menu
            // stays put while typing.
            mention = {...next, snapshot: commandSnapshot(), anchor: getTextareaCaretRect(textarea, next.start)};
            menuSeenOpen = false;
            setMenuOpen(next.trigger, true);
        } else if (mention && mention.query !== next.query) {
            // Only on an actual edit: keyup and clicks re-sync too, and a new command object
            // would make the menu start its highlight over.
            mention = {...mention, query: next.query};
        }
    }

    /** Drops the typed trigger and its query from the text and puts the caret back where
     *  the trigger was — what the menu changed happens *instead* of the removed text. */
    function consumeMention() {
        const textarea = ref;
        if (!textarea || !mention) {
            return;
        }

        const {start, query} = mention;
        const end = start + 1 + query.length;
        setBody(textarea.value.slice(0, start) + textarea.value.slice(end));
        mention = null;
        dismissedStart = null;
        awaitingFlush = true;

        // The value lands on the element only after the binding flushes, so restore the
        // caret where the mention was on the next frame.
        requestAnimationFrame(() => {
            awaitingFlush = false;
            textarea.focus();
            textarea.setSelectionRange(start, start);
        });
    }

    /** Closes the menu and remembers the mention, so it stays closed while the caret
     *  remains inside it (Escape). */
    function dismissMention() {
        dismissedStart = mention?.start ?? null;
        mention = null;
    }

    /** Closes the menu without dismissing the mention — it reopens when the caret is
     *  back inside the same mention. */
    function hideMention() {
        const current = mention;
        // Dropped first, so the effect above doesn't take the closing menu for a dismissal.
        mention = null;
        if (current) {
            setMenuOpen(current.trigger, false);
        }
    }

    function handleInput(e: Event) {
        syncMention(e.target as HTMLTextAreaElement);
    }

    /** Keeps the menu in sync when the caret moves without editing (clicks, arrow keys). */
    function handleSelectionChange(e: Event) {
        syncMention(e.target as HTMLTextAreaElement);
    }

    /** Menu-first key handling; returns true when the key was consumed by the menu. */
    function handleMentionKeyDown(e: KeyboardEvent): boolean {
        if (!mention || !menuOpen) {
            return false;
        }

        switch (e.key) {
            case 'ArrowDown':
                return menu?.move(1) ?? false;
            case 'ArrowUp':
                return menu?.move(-1) ?? false;
            case 'Enter':
            case 'Tab':
                if (e.shiftKey) {
                    return false;
                }
                return menu?.selectActive() ?? false;
            case 'Escape':
                setMenuOpen(mention.trigger, false);
                return true;
            default:
                return false;
        }
    }

    function handleKeyDown(e: KeyboardEvent) {
        // The menu owns Enter/Tab/arrows/Escape while a command has it open, so Enter
        // picks an assistant or tool instead of sending the message.
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
            onclick={handleSelectionChange}
            onkeyup={handleSelectionChange}
            aria-haspopup={mention ? 'menu' : undefined}
            aria-expanded={mention ? menuOpen : undefined}
            class="chat-textarea"
            rows={1}
            aria-describedby={hintId}
            placeholder={textareaPlaceholder}
            ariaLabel={textareaLabel}
        />
        <span id={hintId} class="u-sr-only">{textareaHint}</span>
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
