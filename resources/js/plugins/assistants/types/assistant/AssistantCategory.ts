import z from 'zod';

/**
 * The single subject area an assistant belongs to (at most one per assistant).
 * Picked from a server-provided list; unlike {@link import('./AssistantTag').AssistantTag},
 * categories cannot be created by users.
 */
export const AssistantCategorySchema = z.object({
    id: z.string(),
    text: z.string()
});

export type AssistantCategory = z.infer<typeof AssistantCategorySchema>;
