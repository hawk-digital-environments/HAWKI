import { useApp } from '$lib/app/hooks/useApp.svelte';
import { logApiError } from '$plugins/assistants/api/errors';
import type { AssistantReviewAction, AssistantReviewLog } from '$plugins/assistants/api/schemas/resources/assistant-review-log.schema';
import type { AssistantFieldFlag } from '$plugins/assistants/api/schemas/resources/assistant-field-flag.schema';
import type { AttachmentReviewStatus } from '$plugins/assistants/types/UploadFile';

const ASSISTANTS = 'assistants';
const ASSISTANT_REVIEW_LOGS = 'assistant-review-logs';
const ASSISTANT_FIELD_FLAGS = 'assistant-field-flags';
const ASSISTANT_REVIEWS = 'assistant-reviews';

/** The Publishing Center's administrative log for one assistant, newest first. */
export async function getAssistantReviewLogs(assistantId: string): Promise<AssistantReviewLog[]> {
    try {
        const collection = await useApp().restApi.getResourceCollection(ASSISTANT_REVIEW_LOGS, {
            query: { filter: { assistant_id: assistantId } }
        });
        return Array.from(collection);
    } catch (err) {
        throw logApiError('getAssistantReviewLogs', err, { assistantId });
    }
}

/** Every flag (resolved and unresolved) on one assistant. */
export async function getAssistantFieldFlags(assistantId: string): Promise<AssistantFieldFlag[]> {
    try {
        const collection = await useApp().restApi.getResourceCollection(ASSISTANT_FIELD_FLAGS, {
            query: { filter: { assistant_id: assistantId } }
        });
        return Array.from(collection);
    } catch (err) {
        throw logApiError('getAssistantFieldFlags', err, { assistantId });
    }
}

/** Flags a field (optionally a selected excerpt of it) with a comment. */
export async function createAssistantFieldFlag(
    assistantId: string,
    field: string,
    comment: string,
    excerpt: string | null
): Promise<AssistantFieldFlag> {
    try {
        return await useApp().restApi.createResource(
            ASSISTANT_FIELD_FLAGS,
            { field, comment, excerpt },
            { relationships: { assistant: { data: { type: ASSISTANTS, id: assistantId } } } }
        );
    } catch (err) {
        throw logApiError('createAssistantFieldFlag', err, { assistantId, field });
    }
}

/** Toggles a flag's resolved state. */
export async function setAssistantFieldFlagResolved(id: string, resolved: boolean): Promise<AssistantFieldFlag> {
    try {
        return await useApp().restApi.updateResource(ASSISTANT_FIELD_FLAGS, id, { resolved });
    } catch (err) {
        throw logApiError('setAssistantFieldFlagResolved', err, { id, resolved });
    }
}

/** Permanently removes a flag (not just marking it resolved). */
export async function deleteAssistantFieldFlag(id: string): Promise<void> {
    try {
        await useApp().restApi.deleteResource(ASSISTANT_FIELD_FLAGS, id);
    } catch (err) {
        throw logApiError('deleteAssistantFieldFlag', err, { id });
    }
}

/**
 * Approve / ask for edit (deny) / discard (block) the assistant's pending
 * review. `reason` is required by the backend for `denied` and `blocked`.
 */
export async function submitAssistantReview(
    reviewId: string,
    status: AssistantReviewAction,
    reason?: string
): Promise<void> {
    try {
        await useApp().restApi.updateResource(ASSISTANT_REVIEWS, reviewId, { status, reason: reason ?? null });
    } catch (err) {
        throw logApiError('submitAssistantReview', err, { reviewId, status });
    }
}

/** Sets an admin's ok/corrupted/inadequate judgment on one attachment. */
export async function reviewAssistantAttachment(
    assistantId: string,
    fileId: string,
    reviewStatus: AttachmentReviewStatus
): Promise<void> {
    try {
        await useApp().restApi.postToResourceAction(
            ASSISTANTS,
            `${assistantId}/actions/attachment/review`,
            { fileId, reviewStatus }
        );
    } catch (err) {
        throw logApiError('reviewAssistantAttachment', err, { assistantId, fileId, reviewStatus });
    }
}
