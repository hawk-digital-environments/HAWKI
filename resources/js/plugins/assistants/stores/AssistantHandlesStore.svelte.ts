import type {DataStore} from '$lib/kernel/stores/types.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {AiAssistant} from '$plugins/core/stores/AiHandleStore.svelte.js';
import type {Assistant} from '$plugins/assistants/types/assistant';
import {assistantRowAppearance} from '$plugins/assistants/utils/assistantRowAppearance';
import {
    ASSISTANT_LIST_INCLUDES,
    listAssistants,
    toggleAssistantFavorite
} from '$plugins/assistants/api/resources/assistantsClient.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'assistant-handles': AssistantHandlesStore;
    }
}

const PAGE_SIZE = 50;
/** Safety cap for the page loop in `ensureLoaded`, so a broken `hasNextPage`
 *  cannot turn the lazy menu load into an endless fetch. */
const MAX_PAGES = 10;

/** `Assistant` narrowed to the rows the `@` menu can actually offer: drafts
 *  have no `handle` until they are released, and an untaggable row has no
 *  place in a menu whose whole purpose is addressing. */
type TaggableAssistant = Assistant & {id: string; handle: string};

function isTaggable(assistant: Assistant): assistant is TaggableAssistant {
    return assistant.id !== null && assistant.handle !== null;
}

/**
 * Every assistant visible to the user, held for the composer's `@` menus.
 *
 * The assistants plugin contributes these rows through the `aiAssistants`
 * hook (see `assistants.plugin.ts`): reading {@link menuAssistants} lazily
 * kicks off {@link ensureLoaded}, and the `$state` list fills in reactively
 * when the response lands — every consumer that reads it inside a `$derived`
 * (the `@` button menu, the caret mention popup, the chips) re-renders on
 * its own.
 *
 * Rows are grouped by the assistant's real category and favourites are
 * flagged `pinned`, which the menu lifts into its "Pinned" section; the pin
 * button on such a row toggles the server-side favourite
 * (see {@link toggleFavorite}).
 *
 * Singleton on purpose (the `@` menus are session-global), following the
 * `assistantOptionsStore` pattern: registered with the store extension but
 * without `loadData`, so nothing is fetched until a menu is first opened.
 */
class AssistantHandlesStore implements DataStore {
    public readonly name = 'assistant-handles';

    assistants = $state<Assistant[]>([]);
    loaded = $state(false);
    loading = $state(false);
    error = $state<string | null>(null);

    /** Fetches every visible assistant, following the API's pagination until
     *  the last page. Idempotent: concurrent or repeated calls coalesce into
     *  the one in-flight load. */
    public async ensureLoaded(): Promise<void> {
        if (this.loaded || this.loading) {
            return;
        }
        this.loading = true;
        this.error = null;

        const collected: Assistant[] = [];
        try {
            for (let page = 1; page <= MAX_PAGES; page++) {
                const result = await listAssistants({
                    include: [...ASSISTANT_LIST_INCLUDES],
                    page: {number: page, size: PAGE_SIZE}
                });
                collected.push(...result.assistants);
                if (!result.pagination?.hasNextPage) {
                    break;
                }
            }
            this.assistants = collected;
            this.loaded = true;
        } catch (err) {
            // No toast here: the store loads lazily from a `$derived` (there
            // is no component context to anchor one to). The menus simply
            // keep showing what they have — the HAWKI row — and the error is
            // surfaced on the store for anyone who wants to report it.
            this.error = String(err);
            console.error('AssistantHandlesStore: loading assistants for the @ menu failed:', err);
        } finally {
            this.loading = false;
        }
    }

    /** Marks the held list stale: the next `menuAssistants` read re-kicks
     *  {@link ensureLoaded}. The current rows stay in place until the fresh
     *  page lands, so menus never flicker or empty out mid-refresh. */
    public invalidate(): void {
        this.loaded = false;
    }

    /** Toggles the user's favourite on one assistant and mirrors the new flag
     *  into the held list, so pinned sections re-render immediately. */
    public async toggleFavorite(id: string, favorite: boolean): Promise<void> {
        const assistant = this.assistants.find(candidate => candidate.id === id);
        if (!assistant) {
            return;
        }
        await toggleAssistantFavorite(assistant, favorite);
        assistant.isFavorite = favorite;
    }

    /**
     * The store's assistants mapped to the composer's row model. Reading this
     * kicks off {@link ensureLoaded} — deferred through a microtask, because
     * the read happens inside the consumers' `$derived` and mutating state
     * during its evaluation is not allowed.
     *
     * `translate` comes from the `aiAssistants` hook's context: a category's
     * `text` is a translation key (`assistants.categories.science`), rendered
     * exactly like the store's category bar renders it.
     */
    public menuAssistants(translate: Translator['translate']): AiAssistant[] {
        if (!this.loaded && !this.loading) {
            queueMicrotask(() => void this.ensureLoaded());
        }
        return this.assistants.filter(isTaggable).map(assistant => ({
            id: `assistant:${assistant.id}`,
            handle: `@${assistant.handle}`,
            label: assistant.name,
            description: assistant.description,
            group: assistant.category
                ? {id: `assistant-category:${assistant.category.id}`, label: translate(assistant.category.text)}
                : undefined,
            pinned: assistant.isFavorite,
            // An assistant defines its own run: model (unless it allows
            // selection) and tools are fixed while it is addressed.
            capabilities: {modelSelect: assistant.allowModelSelect, toolSelect: false},
            // Addressing the assistant binds the exchange to it — the raw
            // handle travels as `payload.assistant_handle`.
            chatBinding: assistant.handle,
            appearance: assistantRowAppearance(assistant),
            onTogglePin: (pinned: boolean) => {
                void this.toggleFavorite(assistant.id, pinned);
            }
        }));
    }
}

export const assistantHandlesStore = new AssistantHandlesStore();
