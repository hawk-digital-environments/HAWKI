import type {AiModelStore} from '$plugins/core/stores/AiModelStore.svelte.js';
import {ReleaseMode, type Assistant} from '$plugins/assistants/types/assistant';

/**
 * Why an assistant cannot run right now, judged by the model it runs on:
 *
 * - `deactivated`: the model is no longer offered to the user (deactivated,
 *   removed from the model list, or not accessible). Nothing is persisted:
 *   the assistant becomes available again as soon as its creator picks a
 *   model that is offered.
 * - `offline`: the model is offered, but the model status check reports it
 *   as unreachable (e.g. its provider is down).
 */
export type AssistantUnavailability = 'deactivated' | 'offline';

/**
 * Returns why `assistant` cannot run right now, or `null` when it can.
 *
 * Drafts are never judged (they are not runnable chat participants anyway and
 * may not have a model yet), and nothing is judged before the model list has
 * loaded, so assistants do not flash as deactivated during boot.
 */
export function assistantUnavailability(
    assistant: Pick<Assistant, 'model' | 'releaseStage'>,
    models: AiModelStore
): AssistantUnavailability | null {
    if (assistant.releaseStage === ReleaseMode.DRAFT || models.authorizationState !== 'ready') {
        return null;
    }
    const model = models.getOneById(assistant.model);
    if (!model) {
        return 'deactivated';
    }
    return model.status === 'offline' ? 'offline' : null;
}
