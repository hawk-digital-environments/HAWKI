import {useApp} from '$lib/app/hooks/useApp.svelte.js';
import {
    AssistantReviewWireSchema,
    ReviewPermissionsBodySchema,
    ReviewTargetWireSchema,
    type ReviewQueueRow,
    type ReviewStatus,
    toQueueRow
} from './schemas.js';

/**
 * Thin client over the review workflow's existing assistant API surface —
 * the "simple admin" deliberately adds no endpoints of its own:
 *
 * - queue: `GET /assistant-reviews?filter[status]=…&include=assistant`
 * - target: `GET /assistants/{id}?include=assistant_review,assistant_versions,creator`
 * - decision: `PATCH /assistant-reviews/{id}` (approve / deny / needs_revision)
 * - rollback: `POST /assistants/{id}/actions/rollback`
 * - permissions: `GET /assistant-reviews/actions/permissions` (visibility probe)
 */

const ASSISTANTS = 'assistants';
const REVIEWS = 'assistant-reviews';

export interface ReviewQueuePage {
    rows: ReviewQueueRow[];
    total: number;
}

/**
 * One queue page. A `null` status omits the filter — every accessible
 * review, mixed ("All" tab).
 */
export async function listReviewQueue(
    status: ReviewStatus | null,
    page: {number: number; size: number},
    signal?: AbortSignal
): Promise<ReviewQueuePage> {
    const app = useApp();
    const raw = await app.restApi.getResourceCollection(REVIEWS, {
        query: {
            ...(null !== status ? {filter: {status}} : {}),
            include: ['assistant'],
            page,
            sort: ['-created_at']
        },
        signal,
        validateSchema: false
    });

    const reviews = AssistantReviewWireSchema.array().parse(raw);
    return {
        rows: reviews.map(toQueueRow),
        total: raw?._pagination?.itemCount ?? reviews.length
    };
}

export async function getReviewTarget(assistantId: string, signal?: AbortSignal) {
    const app = useApp();
    const raw = await app.restApi.getResource(ASSISTANTS, assistantId, {
        query: {
            include: [
                'assistant_review',
                'assistant_versions',
                'creator',
                'assistant_category',
                'assistant_avatar',
                'assistant_attachments',
                'assistant_tags',
                'assistant_user_prompts',
                'assistant_setting_values.setting',
                'ai_tools'
            ]
        },
        signal,
        validateSchema: false
    });

    return ReviewTargetWireSchema.parse(raw);
}

export type ReviewDecision = {
    status: Extract<ReviewStatus, 'approved' | 'denied' | 'needs_revision'>;
    /** Required for denials and revision requests; the API rejects without. */
    reason?: string;
};

export async function submitReviewDecision(reviewId: string, decision: ReviewDecision): Promise<void> {
    const app = useApp();
    await app.restApi.updateResource(REVIEWS, reviewId, {
        status: decision.status,
        ...(decision.reason ? {reason: decision.reason} : {})
    });
}

export async function rollbackAssistant(assistantId: string, version: number): Promise<void> {
    const app = useApp();
    await app.restApi.postToResourceAction(ASSISTANTS, `${assistantId}/actions/rollback`, {
        data: {
            type: ASSISTANTS,
            id: assistantId,
            attributes: {version}
        }
    });
}

/**
 * May the signed-in user decide reviews at all — the class-level
 * `updateAny` verdict. Visibility only; per-review decisions stay governed
 * by the resource policies.
 */
export async function mayDecideReviews(signal?: AbortSignal): Promise<boolean> {
    const app = useApp();
    const body = await app.restApi.getFromResourceAction(REVIEWS, 'actions/permissions', {
        signal,
        schema: ReviewPermissionsBodySchema
    });

    return body.data.updateAny === 'ALLOWED';
}

