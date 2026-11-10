import {ApiError} from "$plugins/assistants/api/errors";
import {toggleAssistantFavorite} from "$plugins/assistants/api/resources/assistantsClient";
import type {ToastContext} from "$lib/components/ui/toast/ToastContext.svelte.js";
import type {Assistant} from "$plugins/assistants/types/assistant/Assistant";

/** Translated label lookup, as provided by `useTranslator`'s `__`. */
type Translate = (label: string, replacements?: Record<string, string>) => string;

/**
 * Persists a favourite toggle. The UI state is optimistic (FavButton flips
 * itself on click), so on failure this toasts the reason and rethrows —
 * letting FavButton roll its flip back instead of leaving a lied-about state.
 */
export async function persistFavourite(
    assistant: Assistant,
    active: boolean,
    toast: ToastContext,
    __: Translate,
): Promise<void> {
    try {
        await toggleAssistantFavorite(assistant, active);
    } catch (err) {
        toast.error(`${__('assistants.detail.favourite_failed')} ${ApiError.from(err).userMessage}`);
        throw err;
    }
}
