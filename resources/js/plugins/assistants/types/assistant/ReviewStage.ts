import z from 'zod';

/**
 * Where an assistant sits in the release-review workflow after its creator has
 * requested a release stage beyond `private`. Reported back through
 * {@link import('./Review').Review}.
 *
 * Kept as a TypeScript enum so the members can be used as values in the UI;
 * {@link ReviewStageSchema} is the matching Zod validator.
 */
export enum ReviewStage {
    Submitted = 'submitted',
    Approved = 'approved',
    Rejected = 'rejected'
}

export const ReviewStageSchema = z.enum(ReviewStage);
