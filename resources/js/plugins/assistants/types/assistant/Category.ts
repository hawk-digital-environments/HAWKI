import z from 'zod';

/**
 * The single subject area an assistant belongs to (at most one per assistant).
 * Picked from a server-provided list; unlike {@link import('./Tag').Tag},
 * categories cannot be created by users.
 */
export const CategorySchema = z.object({
    id: z.string(),
    text: z.string()
});

export type Category = z.infer<typeof CategorySchema>;
