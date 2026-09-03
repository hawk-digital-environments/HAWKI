import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'ai-handle': AiHandleStore;
    }
}

/**
 * A taggable AI participant of a room chat, addressed by its `@handle`.
 *
 * The HAWKI assistant itself is one of these (see {@link AiHandleStore.hawkiAssistant});
 * the study assistants next to it are currently **mock data** (see
 * {@link AiHandleStore.studyAssistants}) until the assistant system exists server-side.
 */
export interface AiAssistantHandle {
    /** Stable id, used as the list key in the `@` tagging menu. */
    id: string;
    /** The `@handle` inserted into the message, including the leading `@`. */
    handle: string;
    /** Translation key for the assistant's display name. */
    labelKey: string;
    /** Translation key for the one-line description shown under the name. */
    descriptionKey: string;
}

/**
 * Store for recognized `@handle` mentions in chat messages.
 *
 * Recognizes the configured HAWKI handle (e.g. `@hawki`) plus the (currently mocked)
 * study assistants — `getHandlesIn` is the single place where handles are looked up,
 * so replacing the mock list with server-provided assistants is enough to make the
 * whole `@` tagging flow real.
 *
 * Access via `useStore('ai-handle')`.
 */
export class AiHandleStore implements DataStore {
    public readonly name = 'ai-handle';

    private _hawkiHandle = $state<string | null>(null);

    public get hawkiHandle(): string {
        if (this._hawkiHandle === null) {
            throw new Error('HAWKI handle has not been set yet');
        }
        return this._hawkiHandle;
    }

    /** The HAWKI assistant itself, in the same shape as the study assistants,
     *  so the `@` tagging menu can render it as just another row. */
    public get hawkiAssistant(): AiAssistantHandle {
        return {
            id: 'hawki',
            handle: this.hawkiHandle,
            labelKey: 'chat.composer.assistantMenu.assistants.hawki',
            descriptionKey: 'chat.composer.assistantMenu.assistants.hawkiDescription'
        };
    }

    /** Every taggable assistant, HAWKI first. Empty until the store has loaded, so UI that
     *  renders before `loadData` (e.g. the composer's `@` menu) doesn't hit the
     *  `hawkiHandle` guard. */
    public get assistants(): AiAssistantHandle[] {
        if (this._hawkiHandle === null) {
            return [];
        }
        return [this.hawkiAssistant];
    }

    /**
     * Generator that yields every recognized `@handle` found in `message`, together with
     * its position — `[start, end)` offsets into `message`.
     *
     * Handles must start with `@`, consist of letters/digits/underscores/hyphens,
     * and be delimited by whitespace or appear at the start/end of the string.
     * Matches the HAWKI handle and every known study assistant.
     *
     * Used by the composer to render each typed handle as a pill; use
     * {@link getHandlesIn} when only the handle names matter.
     */
    public* getHandleMatchesIn(message: string): Generator<{ handle: string; start: number; end: number }> {
        // Handles are a string starting with an @, followed by the handle name which can contain letters, numbers,
        // underscores, and hyphens. They must be separated from other words by spaces or be at the start/end
        // of the message.
        const genericHandleRegex = /(^|\s)(@[a-zA-Z0-9_-]+)(?=\s|$)/g;
        const knownHandles = new Set(this.assistants.map(assistant => assistant.handle));
        if (knownHandles.size === 0) {
            return;
        }

        const text = message + '';
        let match;
        while ((match = genericHandleRegex.exec(text)) !== null) {
            const handle = match[2];
            if (!knownHandles.has(handle)) {
                continue;
            }
            // match.index points at the leading delimiter (group 1), not at the "@".
            const start = match.index + match[1].length;
            yield {handle, start, end: start + handle.length};
        }
    }

    /**
     * Generator that yields every recognized `@handle` found in `message`.
     * Positions are available through {@link getHandleMatchesIn}.
     */
    public* getHandlesIn(message: string): Generator<string> {
        for (const match of this.getHandleMatchesIn(message)) {
            yield match.handle;
        }
    }

    public async loadData(app: HawkiApp): Promise<void> {
        this._hawkiHandle = app.config.get().ai?.handle ?? '@hawki';
    }
}
