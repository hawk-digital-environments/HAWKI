import z from 'zod';

/**
 * Schema for a single `user-favorites` resource — one favorited item of the
 * requesting user, addressed by the (`namespace`, `item_type`, `identifier`)
 * triple.
 *
 * Note the wire name `item_type`: the model column is `type`, but JSON:API
 * reserves the member name `type`, so the API exposes it as `item_type`. The
 * favorites extension maps it back to `type` for consumers.
 *
 * Fetched (and auto-validated) via `restApi.getResourceCollection('user-favorites')`
 * during the favorites extension's `preparation` boot stage.
 */
const UserFavoriteSchema = z.object({
    /** Row id (used to DELETE the favorite). */
    id: z.string(),
    /** Logical owner of the favorite (e.g. `'hawki-core'`). */
    namespace: z.string(),
    /** Kind of favorited item (wire name of the logical `type`; e.g. `'ai-model'`). */
    item_type: z.string(),
    /** The favorited item's id. */
    identifier: z.string()
});

export default UserFavoriteSchema;

/** Validated shape of a single `user-favorites` resource. */
export type UserFavoriteResource = z.infer<typeof UserFavoriteSchema>;

// Augment the resource schema registry so `restApi.*('user-favorites', ...)` is typed.
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'user-favorites': UserFavoriteResource;
    }
}
