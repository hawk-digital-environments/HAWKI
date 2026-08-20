import z from 'zod';

/** Validates the public profile returned by the core `users` resource. */
const UsersSchema = z.object({
    id: z.string(),
    display_name: z.string(),
    username: z.string(),
    email: z.string().nullable().optional(),
    bio: z.string().nullable().optional(),
    /** Stored-file identifier; build its URL through `app.uriBuilder`. */
    avatar: z.string().nullable().optional(),
    employee_type: z.string().nullable().optional(),
    created_at: z.string().nullable().optional(),
    updated_at: z.string().nullable().optional()
});

export default UsersSchema;

export type User = z.infer<typeof UsersSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        users: User;
    }
}
