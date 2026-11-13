import z from 'zod';

const AuthLinks = z.object({redirect: z.string().min(1)}).optional();

const AuthSchema = z.discriminatedUnion('mode', [
    z.object({id: z.literal('hawki'), mode: z.literal('credentials'), last_error: z.enum(['invalid_credentials', 'provider_failed']).nullable(), _links: AuthLinks}),
    z.object({id: z.literal('hawki'), mode: z.literal('redirect'), last_error: z.enum(['invalid_credentials', 'provider_failed']).nullable(), _links: AuthLinks})
]);

export const RegistrationPolicySchema = z.object({
    policy: z.object({
        id: z.coerce.string().min(1),
        locale: z.string().min(1),
        text: z.string(),
        hash: z.string().min(1)
    }).nullable(),
    consent: z.enum(['valid', 'required'])
});

export default AuthSchema;
export type Auth = z.infer<typeof AuthSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas { auth: Auth; }
}
