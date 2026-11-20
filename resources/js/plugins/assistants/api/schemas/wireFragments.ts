import z from 'zod';

/**
 * Shapes shared by more than one resource's wire schema — as opposed to
 * `resources/*.schema.ts`, which each own the shapes that are only ever
 * embedded in *that* one resource's response.
 */

/** `{href, meta:{message: 'ALLOWED'|'DENIED'}}` action link, or a plain `self` URL string. */
export const WireLinkSchema = z.union([
    z.string(),
    z.object({
        href: z.string().optional(),
        meta: z.object({
            message: z.string().optional(),
            method: z.string().optional()
        }).optional()
    })
]);

/** `users` resource, as included for `creator` / `remix_creator` / feedback `user`. */
export const WireUserSchema = z.object({
    id: z.string(),
    display_name: z.string().nullable().optional(),
    avatar: z.string().nullable().optional()
});

/**
 * A JSON:API resource object as sent in a POST/PATCH request body — the
 * common return shape of every resource's `xToApi()` in `resources/*.schema.ts`.
 */
export interface JsonApiResourceBody {
    type: string;
    id?: string | null;
    attributes?: Record<string, unknown>;
    relationships?: Record<string, unknown>;
}
