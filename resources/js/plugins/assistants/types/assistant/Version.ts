import z from 'zod';

/**
 * One entry in an assistant's revision history, listed on the detail page.
 * Versions are created server-side whenever a released assistant changes.
 */
export const VersionSchema = z.object({
    id: z.string(),
    /** Human-readable changelog note for this revision. */
    text: z.string(),
    /** Version label as shown in the UI, e.g. `"1.2"`. */
    version: z.string(),
    /** Comma-separated list of the assistant fields this revision touched; `null` when the server did not record a diff. */
    changedKeys: z.string().nullable(),
    createdAt: z.string(),
    updatedAt: z.string()
});

export type Version = z.infer<typeof VersionSchema>;
