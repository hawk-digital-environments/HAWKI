import type {AssistantAvatar} from '$plugins/assistants/types/assistant/AssistantAvatar';
import {BACKGROUNDS} from '$plugins/assistants/presets/backgrounds';

/**
 * Resolves the avatar to render for an assistant at display time.
 *
 * Assistants that predate the avatar builder's Erscheinungsbild (background +
 * symbol) have no persisted avatar — for those, a neutral fallback is built
 * from the first background preset and the initial letter of `fallbackName`
 * (usually the assistant's name). Assistants with a persisted avatar are
 * passed through unchanged.
 *
 * Single owner of the legacy-fallback rule; consumers are the store card
 * (`AssistantCard.svelte`) and the detail page.
 */
export function resolveAssistantAvatar(
    avatar: AssistantAvatar | null | undefined,
    fallbackName: string,
): AssistantAvatar {
    return avatar ?? {
        iconCss: BACKGROUNDS[0].value,
        name: fallbackName.slice(0, 1) || '?',
    };
}
