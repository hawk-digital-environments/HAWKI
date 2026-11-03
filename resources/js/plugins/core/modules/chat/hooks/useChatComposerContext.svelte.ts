/**
 * # Chat Composer Context — the chat section's composer
 *
 * `useChatComposerContext()` is the composition root for the composer on the
 * routed chat pages (`ChatIndex` at `chat.index`, `ChatConversation` at
 * `chat.conversation`): it builds the `ComposerContext`, wires it to the
 * shared `ChatTransport` (first-message conversation creation, navigation,
 * optimistic pending message) and to the chat store (per-conversation state
 * sync), and exposes a small facade over both. The legacy blade snippets and
 * room chats do not go through this hook; they keep their per-mount composer
 * from `createComposerContext`.
 *
 * ## Ownership — one live composer, per-chat state slots
 *
 * The hook is called once by `ChatLayout.svelte`, the route layout shared by
 * both chat pages. The layout stays mounted while navigating between them,
 * so the context and its `$derived` graph are owned by a tree that outlives
 * every single page. There is exactly **one live composer per layout mount**
 * — its *state*, however, belongs to the chat being viewed:
 *
 * - Every conversation owns a **slot** (`{parameters, tools, message}`), kept
 *   in a module-level map for the whole tab session. Switching chats saves
 *   the leaving conversation's state into its slot and restores the entering
 *   one's — settings and drafts are stable per chat, across any number of
 *   switches.
 * - The "new chat" page has no slot: it always starts from clean defaults.
 * - When the first message **creates** a conversation, the live session is
 *   simply re-keyed to the new slug — parameters, tools and the prompt flow
 *   into the conversation without any reset (the one handoff that exists).
 * - A conversation's system prompt always comes from the conversation record
 *   (persisted server-side), never from a slot.
 *
 * Leaving the chat section unmounts the layout and ends the session; the
 * slots survive (tab session), and an in-flight generation keeps streaming
 * through the shared transport, which is a plain class with no reactive
 * state of its own.
 *
 * Returns a small facade over the context plus the page-level state the pages
 * need:
 *
 * | Member                         | Purpose                                                        |
 * |--------------------------------|----------------------------------------------------------------|
 * | `composer`                     | the `ComposerContext` itself (model, parameters, tools, send)  |
 * | `pendingMessage`               | optimistic first message while the conversation is created     |
 * | `isFocused` / `setFocus(v)`    | holds the composer in its busy state while a response streams  |
 * | `reset()`                      | parameters/tools/prompt back to defaults                       |
 * | `focusInput()` / `autoFocus…()`| cursor into the textarea (auto skips small screens)            |
 */
import {onDestroy} from 'svelte';
import {useApp} from '$lib/app/hooks/useApp.svelte.js';
import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
import type {ToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import {
    type ComposerContext,
    createComposerContext
} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
import {ChatTransport} from '$plugins/core/modules/chat/transport/ChatTransport.js';
import type {ModelParameterSliceCheckpoint} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModelParameterSlice.svelte.js';
import type {ToolSliceCheckpoint} from '$plugins/core/modules/chat/components/composer/contexts/slices/ToolSlice.svelte.js';
import type {ChatStore} from '$plugins/core/stores/ChatStore.svelte.js';
import type {ChatMessage} from '$plugins/core/modules/chat/types.js';

/** The hook's return surface — see the module overview for what each member is for. */
export interface ChatComposerContextApi {
    /** The composer state container owned by the chat layout. */
    readonly composer: ComposerContext;
    /** The first user message, shown optimistically while its conversation is still being created. */
    readonly pendingMessage: ChatMessage | null;
    /** `true` while the composer is held in its busy state (send disabled, response running). */
    readonly isFocused: boolean;
    /** Holds the composer in (`true`) or out of (`false`) its busy state. */
    setFocus(value: boolean): void;
    /** Resets parameters, tools and system prompt back to defaults. */
    reset(): void;
    /** Puts the cursor into the composer's textarea. */
    focusInput(): void;
    /** Like {@link focusInput}, but skipped on small screens where it would pop up the on-screen keyboard. */
    autoFocusInput(): void;
}

/** A conversation's saved composer state — what makes the composer stable per chat. */
interface ChatComposerSlot {
    parameters: ModelParameterSliceCheckpoint;
    tools: ToolSliceCheckpoint;
    /** The draft the user was composing in this conversation. */
    message: string;
}

/** The chat layout's live session; lives and dies with the layout's mount. */
interface ChatComposerSession {
    composer: ComposerContext;
    api: ChatComposerContextApi;
    /** Slug of the conversation the live composer currently belongs to; `null` = "new chat" state. */
    syncedSlug: string | null;
}

let session: ChatComposerSession | null = null;

/** Per-conversation composer slots, keyed by slug. Module-level: they outlive layout mounts and are cleared only by a reload. */
const slots = new Map<string, ChatComposerSlot>();

/** Transport shared across sessions. Plain class — no reactive state, safe to keep alive. */
let transport: ChatTransport | null = null;

/** Optimistic first message while the conversation is being created (see `ChatTransport`). */
const pendingState = $state({message: null as ChatMessage | null});

/**
 * Returns the chat section's composer facade. The first call inside a chat
 * section — the `ChatLayout` mounting, whose script runs before any page's —
 * creates the session; every later call (the pages) is a reader. Call during
 * component initialization: creating registers a store-sync effect tied to
 * the caller's lifetime.
 */
export function useChatComposerContext(): ChatComposerContextApi {
    if (session) {
        return session.api;
    }

    const app = useApp();
    const toast = useToastContext();
    const store = app.stores.get('chat');
    const sharedTransport = transport ??= createTransport(app);

    const composer = createComposerContext(app, 'aiConv', toast, {
        transport: sharedTransport,
        useLegacyBridge: false,
        // Persist prompt edits on the open conversation. The equality check
        // doubles as the echo guard for prompts pushed *from* the store.
        onSetSystemPrompt: prompt => {
            const active = store.active;
            if (active && active.system_prompt !== prompt) {
                void store.updateSystemPrompt(active.slug, prompt);
            }
        },
        onImproveMessage: (message, systemPrompt) => sharedTransport.improveMessage(message, systemPrompt)
    });

    registerSyncEffect(store, composer);

    const currentSession: ChatComposerSession = {
        composer,
        api: {
            composer,
            get pendingMessage() {
                return pendingState.message;
            },
            get isFocused() {
                return composer.forcedActive;
            },
            setFocus(value: boolean) {
                composer.forcedActive = value;
            },
            reset() {
                composer.reset(true);
            },
            focusInput() {
                composer.focusInput();
            },
            autoFocusInput() {
                composer.autoFocusInput();
            }
        },
        syncedSlug: null
    };

    session = currentSession;

    // The creating mount (the chat layout) is the chat section: when it goes,
    // the session goes with it. The slots and the transport survive, so
    // per-chat state is restored on the next visit and a generation that is
    // still streaming keeps writing into the store.
    onDestroy(() => {
        if (session === currentSession) {
            session = null;
        }
    });

    return currentSession.api;
}

function createTransport(app: HawkiApp): ChatTransport {
    return new ChatTransport(app, app.stores.get('chat'), {
        // Re-key the live session *before* navigating: the conversation page
        // then continues this session without a reset — that continuation is
        // what keeps the user's settings alive in the created conversation.
        onConversationCreated: slug => {
            if (session) {
                session.syncedSlug = slug;
            }
            // Do not pull the user back if they switched chats while the
            // conversation was being created.
            if (app.router.isActive('chat.index')) {
                void app.router.goToRoute('chat.conversation', {slug});
            } else {
                // The index page — the only consumer of the optimistic
                // message — is no longer open; drop it so it cannot leak
                // into a later "new chat" visit.
                pendingState.message = null;
            }
        },
        onConversationPending: message => {
            pendingState.message = message;
        }
    });
}

/** Saves the live composer's current state as `slug`'s slot. */
function saveSlot(composer: ComposerContext, slug: string): void {
    slots.set(slug, {
        parameters: composer.modelParameters.createCheckpoint(),
        tools: composer.tools.createCheckpoint(),
        message: composer.message
    });
}

/**
 * Keeps the live composer aligned with the chat store for the layout's whole
 * lifetime — the one place composer state moves between chats:
 *
 * - entering the "new chat" state saves the leaving conversation's slot and
 *   resets the composer to clean defaults,
 * - opening (or switching to) a conversation saves the leaving slot and
 *   restores the entering one's, falling back to defaults plus the
 *   conversation's own system prompt,
 * - the conversation just created from the current settings continues the
 *   session without any reset (see `createTransport`).
 */
function registerSyncEffect(store: ChatStore, composer: ComposerContext): void {
    $effect(() => {
        if (store.loading) return;
        const active = store.active;

        if (!active) {
            // While the first message is creating its conversation, keep
            // everything the user configured.
            if (pendingState.message) return;
            // Entering the "new chat" state: the leaving conversation keeps
            // its state in its slot, the composer itself starts fresh.
            if (session?.syncedSlug) {
                saveSlot(composer, session.syncedSlug);
                session.syncedSlug = null;
                composer.reset(true);
            }
            return;
        }

        if (session && active.slug === session.syncedSlug) {
            // Arrived at (or still on) the conversation this composer was
            // synced to — including the one just created from these settings.
            pendingState.message = null;
            return;
        }

        // Opening another conversation: the leaving one keeps its state, the
        // entering one gets its own slot back (or defaults, then its prompt).
        if (session?.syncedSlug) {
            saveSlot(composer, session.syncedSlug);
        }
        if (session) {
            session.syncedSlug = active.slug;
        }
        pendingState.message = null;
        composer.reset(true);
        const slot = slots.get(active.slug);
        if (slot) {
            composer.modelParameters.restoreCheckpoint(slot.parameters);
            composer.tools.restoreCheckpoint(slot.tools);
            composer.message = slot.message;
        }
        if (composer.systemPrompt !== active.system_prompt) {
            composer.systemPrompt = active.system_prompt;
        }
    });
}
