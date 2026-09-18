import z from 'zod';
import { ReviewStageSchema } from './ReviewStage';

/**
 * The outcome of a release review: where the request currently stands and the
 * reviewer's justification for a denial.
 */
export const ReviewSchema = z.object({
    /**
     * The `assistant_reviews` row id — admin actions (approve/deny/needs-revision)
     * PATCH this resource by id. Optional: a freshly (re)opened review built
     * client-side by `BuilderContext.requestRelease()`'s optimistic update
     * doesn't know the server-assigned id yet.
     */
    id: z.string().optional(),
    status: ReviewStageSchema,
    /** Reviewer note explaining a denial — shown verbatim to the creator. */
    reason: z.string().nullable()
});

export type Review = z.infer<typeof ReviewSchema>;
