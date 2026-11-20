import z from 'zod';

/**
 * The user an assistant is attributed to — either its author or, for a remix,
 * the author of the original (see `Assistant.remixCreator`). A display-only
 * projection of the user resource: just enough to render a byline.
 */
export const CreatorSchema = z.object({
    id: z.string(),
    displayName: z.string(),
    /** Avatar URL. Absent when the user has not uploaded one; render initials instead. */
    avatar: z.string().optional()
});

export type Creator = z.infer<typeof CreatorSchema>;
