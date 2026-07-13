import z from 'zod';
import { ReviewStage } from './ReviewStage';

/**
 * The outcome of a release review: where the request currently stands and the
 * moderator's justification for it.
 */
export const ReviewSchema = z.object({
    /** One of {@link ReviewStage} for the known stages, or an arbitrary string for a stage this frontend does not model yet. */
    status: z.union([z.enum(ReviewStage), z.string()]),
    /** Moderator note explaining the decision — shown verbatim to the creator. */
    reason: z.string()
});

export type Review = z.infer<typeof ReviewSchema>;
