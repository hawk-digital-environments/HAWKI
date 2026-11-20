import z from 'zod';

/**
 * Where an assistant sits in the release-review workflow after its creator has
 * requested a release stage beyond `private`. Reported back through
 * {@link import('./Review').Review}.
 *
 * `DENIED` is permanent — the creator cannot resubmit. `NEEDS_REVISION` is a
 * soft denial: the assistant was sent back, but the creator may revise and
 * submit it again (which reopens the review as `PENDING`).
 *
 * Kept as a TypeScript enum so the members can be used as values in the UI;
 * {@link ReviewStageSchema} is the matching Zod validator.
 */
export enum ReviewStage {
    PENDING = 'pending',
    APPROVED = 'approved',
    DENIED = 'denied',
    NEEDS_REVISION = 'needs_revision'
}

export const ReviewStageSchema = z.enum(ReviewStage);
