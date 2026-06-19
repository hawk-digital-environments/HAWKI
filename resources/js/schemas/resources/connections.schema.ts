import z from 'zod';

export const AuthenticatedUserInfoSchema = z.object({
    /**
     * The unique identifier of the user. This matches on the `id` field of the user object returned by the API.
     */
    id: z.number(),
    name: z.string(),
    username: z.string(),
    email: z.string(),
    avatar: z.string().nullable(),
    bio: z.string().nullable(),
    /**
     * A hash derived by the user id and public key, which can be used to detect if
     * the user profile has been reset on the server. If this hash changes,
     * we must drop all local data, as it is no longer valid.
     */
    hash: z.string()
}).strict();

export const RegisteringUserInfoSchema = AuthenticatedUserInfoSchema.pick({
    name: true,
    username: true,
    email: true
}).loose(); // loose to allow some empty fields so we don't have to create a dedicated data view.

export type AuthenticatedUserInfo = z.infer<typeof AuthenticatedUserInfoSchema>;

const BaseConnectionSchema = z.object({
    id: z.literal('hawki'),
    /**
     * The version of the HAWKI backend API the client is connected to.
     */
    version: z.string(),
    /**
     * The locale of the HAWKI backend.
     * This is treated as the "preferred" locale for the client.
     */
    locale: z.string()
}).strict();

export const InternalConnectionSchema = BaseConnectionSchema.extend({
    type: z.literal('internal')
});

export type InternalConnection = z.infer<typeof InternalConnectionSchema>;

export const InternalAuthenticatedConnectionSchema = BaseConnectionSchema.extend({
    type: z.literal('internal_authenticated'),
    /**
     * Information about the authenticated user. This is only present if the client is authenticated with the HAWKI backend.
     */
    userinfo: AuthenticatedUserInfoSchema,
    migrations_to_apply: z.number().optional()
});

export type InternalAuthenticatedConnection = z.infer<typeof InternalAuthenticatedConnectionSchema>;

export const InternalRegisteringUserConnectionSchema = BaseConnectionSchema.extend({
    type: z.literal('internal_registering_user'),
    /**
     * Information about the user that is currently registering. This is only present if the client is in the process of registering a new user account.
     */
    userinfo: RegisteringUserInfoSchema
});

export type InternalRegisteringUserConnection = z.infer<typeof InternalRegisteringUserConnectionSchema>;

const ConnectionsSchema = z.discriminatedUnion('type', [
    InternalConnectionSchema,
    InternalAuthenticatedConnectionSchema,
    InternalRegisteringUserConnectionSchema
]);

export default ConnectionsSchema;

export type Connection = z.infer<typeof ConnectionsSchema>;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'connections': Connection;
    }
}
