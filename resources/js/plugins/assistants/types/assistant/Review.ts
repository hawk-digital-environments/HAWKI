import z from 'zod';
import { ReviewStageSchema } from './ReviewStage';

/**
 * The outcome of a release review: where the request currently stands and the
 * reviewer's justification for a denial.
 */
export const ReviewSchema = z.object({
    status: ReviewStageSchema,
    /** Reviewer note explaining a denial — shown verbatim to the creator. */
    reason: z.string().nullable()
});

export type Review = z.infer<typeof ReviewSchema>;
