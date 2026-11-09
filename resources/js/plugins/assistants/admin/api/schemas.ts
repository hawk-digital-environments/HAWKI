import z from 'zod';

/**
 * Hardcoded wire schemas for the assistant review admin. Deliberately
 * self-contained (the "simple admin" surface): only the fields the review
 * workflow reads are declared, so the plugin stays decoupled from the
 * assistants plugin's full domain mapping.
 */

/** `App\Services\Assistant\Values\AssistantReviewStatus` on the wire. */
export const ReviewStatusSchema = z.enum(['pending', 'approved', 'denied', 'needs_revision']);
export type ReviewStatus = z.infer<typeof ReviewStatusSchema>;

/** `App\Services\Assistant\Values\AssistantReleaseStage` on the wire. */
export const ReleaseStageSchema = z.enum(['draft', 'private', 'organizational', 'federated']);
export type ReleaseStage = z.infer<typeof ReleaseStageSchema>;

/** The `assistant` include of a review row — just enough for the queue. */
export const ReviewAssistantWireSchema = z.object({
    id: z.string(),
    name: z.string(),
    handle: z.string().nullable().optional(),
    release_stage: ReleaseStageSchema.nullable().optional(),
    requested_release_stage: ReleaseStageSchema.nullable().optional(),
    created_at: z.string().nullable().optional(),
    updated_at: z.string().nullable().optional()
});

/** A row of `GET /assistant-reviews` (`include=assistant`). */
export const AssistantReviewWireSchema = z.object({
    id: z.string(),
    status: ReviewStatusSchema,
    reason: z.string().nullable().optional(),
    created_at: z.string().nullable().optional(),
    updated_at: z.string().nullable().optional(),
    /** Present when requested via `include`. */
    assistant: ReviewAssistantWireSchema.nullable().optional()
});
export type AssistantReviewWire = z.infer<typeof AssistantReviewWireSchema>;

/** One row of the `assistant_versions` include on the assistant detail. */
export const AssistantVersionWireSchema = z.object({
    id: z.string(),
    text: z.string(),
    /** `Number::make('version')` — numeric on the wire; normalized to the one-decimal string (`0.0`, `1.0`, `10.0`). */
    version: z.union([z.string(), z.number()]).transform(value => Number(value).toFixed(1)),
    changed_keys: z.array(z.string()).nullable().optional(),
    created_at: z.string().nullable().optional(),
    updated_at: z.string().nullable().optional()
});
export type AssistantVersionWire = z.infer<typeof AssistantVersionWireSchema>;

/** The assistant detail as the review page reads it (raw wire, own schema). */
export const ReviewTargetWireSchema = z.object({
    id: z.string(),
    name: z.string(),
    handle: z.string().nullable().optional(),
    description: z.string().nullable().optional(),
    detail_description: z.string().nullable().optional(),
    system_prompt: z.string().nullable().optional(),
    greeting: z.string().nullable().optional(),
    model: z.string().nullable().optional(),
    max_tokens: z.number().nullable().optional(),
    temp: z.number().nullable().optional(),
    top_p: z.number().nullable().optional(),
    allow_remix: z.boolean().nullable().optional(),
    allow_model_select: z.boolean().nullable().optional(),
    release_stage: ReleaseStageSchema.nullable().optional(),
    requested_release_stage: ReleaseStageSchema.nullable().optional(),
    created_at: z.string().nullable().optional(),
    updated_at: z.string().nullable().optional(),
    /** Present when requested via `include`. */
    assistant_review: z.object({
        id: z.string(),
        status: ReviewStatusSchema,
        reason: z.string().nullable().optional(),
        /** When the decision was made; null while pending. */
        reviewed_at: z.string().nullable().optional(),
        /** Fallback when no explicit decision date exists. */
        updated_at: z.string().nullable().optional()
    }).nullable().optional(),
    assistant_versions: z.array(AssistantVersionWireSchema).nullable().optional(),
    creator: z.object({
        id: z.string(),
        display_name: z.string().nullable().optional()
    }).nullable().optional(),
    /** Relation summaries for changed-key entries. */
    assistant_category: z.object({name: z.string().nullable().optional()}).loose().nullable().optional(),
    assistant_avatar: z.object({
        name: z.string().nullable().optional(),
        icon_css: z.string().nullable().optional()
    }).loose().nullable().optional(),
    assistant_attachments: z.array(z.object({
        name: z.string().nullable().optional(),
        mime: z.string().nullable().optional()
    }).loose()).nullable().optional(),
    assistant_tags: z.array(z.object({text: z.string().nullable().optional()}).loose()).nullable().optional(),
    assistant_user_prompts: z.array(z.object({text: z.string().nullable().optional()}).loose()).nullable().optional(),
    assistant_setting_values: z.array(z.object({
        value: z.unknown().nullable().optional(),
        setting: z.object({key: z.string().nullable().optional()}).loose().nullable().optional()
    }).loose()).nullable().optional(),
    ai_tools: z.array(z.object({name: z.string().nullable().optional()}).loose()).nullable().optional()
});
export type ReviewTargetWire = z.infer<typeof ReviewTargetWireSchema>;

/** The version number of the mutable working-draft row (`0.0`). */
export const DRAFT_VERSION = '0.0';

/**
 * Verdict of `GET /assistant-reviews/actions/permissions`: the class-level
 * ability probe in the action-links ALLOWED/DENIED vocabulary. Drives the
 * admin surface's visibility; the per-review policies stay the boundary.
 */
export const ReviewPermissionsWireSchema = z.object({
    updateAny: z.enum(['ALLOWED', 'DENIED'])
});
export type ReviewPermissionsWire = z.infer<typeof ReviewPermissionsWireSchema>;

/** The probe's response body (plain JSON, not a JSON:API document). */
export const ReviewPermissionsBodySchema = z.object({
    data: ReviewPermissionsWireSchema
});
export type ReviewPermissionsBody = z.infer<typeof ReviewPermissionsBodySchema>;

/** Queue row mapped for the UI. */
export interface ReviewQueueRow {
    reviewId: string;
    assistantId: string;
    name: string;
    handle: string | null;
    status: ReviewStatus;
    stage: ReleaseStage | null;
    requestedStage: ReleaseStage | null;
    submittedAt: string | null;
    reason: string | null;
}

export function toQueueRow(review: AssistantReviewWire): ReviewQueueRow {
    return {
        reviewId: review.id,
        assistantId: review.assistant?.id ?? '',
        name: review.assistant?.name ?? '',
        handle: review.assistant?.handle ?? null,
        status: review.status,
        stage: review.assistant?.release_stage ?? null,
        requestedStage: review.assistant?.requested_release_stage ?? null,
        submittedAt: review.created_at ?? null,
        reason: review.reason ?? null
    };
}

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'assistant-reviews': AssistantReviewWire;
    }
}
