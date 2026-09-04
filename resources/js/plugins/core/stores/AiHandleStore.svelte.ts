import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import {defaultAssistantAppearance, type AssistantAppearance} from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'ai-handle': AiHandleStore;
    }
    interface HawkiHooks {
        aiAssistants: {value: AiAssistant[]; ctx: AiAssistantsContext};
    }
}

/**
 * A taggable AI participant of a room chat, addressed by its `@handle`.
 *
 * The HAWKI assistant itself is the store-provided base entry (see
 * {@link AiHandleStore.hawkiAssistant}); everything else is contributed by
 * other plugins through the `aiAssistants` hook — e.g. the assistants
 * plugin appends the user's real assistants there, grouped by their
 * category, with favourites flagged as pinned.
 */
export interface AiAssistant {
    /** Stable id, namespaced by its provider (e.g. `'hawki'`, `'assistant:<uuid>'`). */
    id: string;
    /** The `@handle` inserted into the message, including the leading `@`. */
    handle: string;
    /** Resolved display name — a plain string, not a translation key. */
    label: string;
    /** Resolved one-line description shown under the name. */
    description: string;
    /** Which section of the `@` menu the row belongs to (e.g. the assistant's
     *  category). Rows without a group share the menu's default section. */
    group?: {id: string; label: string};
    /** Server-side pinned hint; lifts the row into the menu's "Pinned" section
     *  the same way a local composer pin does. */
    pinned?: boolean;
    /** Presentation override; falls back to the shared default appearance. */
    appearance?: AssistantAppearance;
    /** Server-side pin toggle for this row (e.g. toggling the favourite).
     *  Absent = the row pins through the local `composer-pins` store. */
    onTogglePin?: (pinned: boolean) => void;
}

/** Context for the `aiAssistants` hook: handlers may resolve labels in the user's language. */
export interface AiAssistantsContext {
    translate: Translator['translate'];
}

/**
 * Store for recognized `@handle` mentions in chat messages.
 *
 * Owns the configured HAWKI handle (e.g. `@hawki`) and exposes every taggable
 * assistant through {@link assistants}: the HAWKI assistant first, then the
 * result of applying the `aiAssistants` hook. `getHandlesIn` is the single
 * place where handles are looked up, so the whole `@` tagging flow — menu,
 * caret popup, chips, composer context — sees the same list.
 *
 * Access via `useStore('ai-handle')`.
 */
export class AiHandleStore implements DataStore {
    public readonly name = 'ai-handle';

    private _app: HawkiApp | null = null;
    private _hawkiHandle = $state<string | null>(null);

    public get hawkiHandle(): string {
        if (this._hawkiHandle === null) {
            throw new Error('HAWKI handle has not been set yet');
        }
        return this._hawkiHandle;
    }

    /** The HAWKI assistant itself, as the base entry every consumer can rely on
     *  being first. Its labels are resolved at read time, so they follow locale
     *  switches like every other UI string. */
    private get hawkiAssistant(): AiAssistant {
        const translate = this._app!.localization.translator.translate;
        const label = translate('chat.composer.assistantMenu.assistants.hawki');
        return {
            id: 'hawki',
            handle: this.hawkiHandle,
            label,
            description: translate('chat.composer.assistantMenu.assistants.hawkiDescription'),
            group: {id: 'hawki', label: translate('chat.composer.assistantMenu.hawkiLabel')},
            // Same icon rule as every other row: the name's first glyph.
            appearance: defaultAssistantAppearance(label)
        };
    }

    /** Every taggable assistant, HAWKI first, then whatever other plugins add
     *  via the `aiAssistants` hook. Empty until the store has loaded, so UI
     *  that renders before `loadData` doesn't hit the `hawkiHandle` guard. */
    public get assistants(): AiAssistant[] {
        if (this._hawkiHandle === null || this._app === null) {
            return [];
        }
        return this._app.hooks.apply('aiAssistants', [this.hawkiAssistant], {
            translate: this._app.localization.translator.translate
        });
    }

    /**
     * Generator that yields every recognized `@handle` found in `message`, together with
     * its position — `[start, end)` offsets into `message`.
     *
     * Handles must start with `@`, consist of letters/digits/underscores/hyphens,
     * and be delimited by whitespace or appear at the start/end of the string.
     * Matches every handle {@link assistants} offers.
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
        this._app = app;
        this._hawkiHandle = app.config.get().ai?.handle ?? '@hawki';
    }
}
