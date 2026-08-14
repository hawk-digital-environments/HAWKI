import z from 'zod';

/**
 * A free-form label attached to an assistant, used for filtering and search on
 * the dashboard. Unlike {@link import('./Category').Category}, tags are
 * user-creatable — see `assistantOptionsStore.addTag()`.
 */
export const TagSchema = z.object({
    id: z.string(),
    text: z.string()
});

export type Tag = z.infer<typeof TagSchema>;
