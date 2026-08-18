import z from 'zod';

/**
 * Validates the `users` API resource — the public profile of a HAWKI user.
 *
 * `email` and `employee_type` are only serialized for admins, `bio`/`avatar`
 * may be null for users that have not filled their profile yet.
 *
 * Registers the resource under the key `'users'` in `HawkiResourceSchemas`
 * (see the `declare module` augmentation below).
 */
const UsersSchema = z.object({
    id: z.string(),
    display_name: z.string(),
    username: z.string(),
    email: z.string().nullable().optional(),
    bio: z.string().nullable().optional(),
    /** Stored-file identifier of the avatar; build a URL via `app.uriBuilder.storageFileUri()`. */
    avatar: z.string().nullable().optional(),
    employee_type: z.string().nullable().optional(),
    created_at: z.string().nullable().optional(),
    updated_at: z.string().nullable().optional()
});

export default UsersSchema;

export type User = z.infer<typeof UsersSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'users': User;
    }
}
