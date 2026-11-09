import type {AiAssistant} from '$plugins/core/stores/AiHandleStore.svelte.js';
import type {Translator} from '$lib/kernel/localization/translator.js';

/** What the regenerate menu picked: the participant that should re-run the
 *  reply — any `AiAssistant` row, HAWKI included — plus the model to use when
 *  the participant allows model selection. Picking a bare model is "HAWKI
 *  with that model". */
export interface ChatRegeneratePick {
    /** The participant the regenerated run is addressed to. */
    participant: AiAssistant;
    /** Model id when the participant allows model selection; `null`/absent keeps
     *  the model that produced the message. Ignored for participants that fix it. */
    model?: string | null;
}

/** One pickable row in a regenerate submenu: a pick plus row metadata. */
export interface ChatRegenerateEntry extends ChatRegeneratePick {
    /** Stable row id, unique within its group. */
    id: string;
    /** True when the row cannot run right now (e.g. its model is offline). */
    disabled?: boolean;
}

/** One submenu in the regenerate menu — a "provider" of regeneration options.
 *  Core contributes the per-provider model groups (each entry HAWKI with one
 *  model); plugins contribute their own participants (e.g. the assistants
 *  plugin's "Assistants" group). */
export interface ChatRegenerateGroup {
    /** Stable group id, namespaced by its provider (e.g. `'assistants'`). */
    id: string;
    /** Submenu label ("Assistants", "OpenAI", …). */
    label: string;
    entries: ChatRegenerateEntry[];
}

/** Context for the `chatRegenerateGroups` hook. */
export interface ChatRegenerateGroupsContext {
    translate: Translator['translate'];
}

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiHooks {
        chatRegenerateGroups: { value: ChatRegenerateGroup[]; ctx: ChatRegenerateGroupsContext };
    }
}
