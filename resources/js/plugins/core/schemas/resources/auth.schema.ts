import z from 'zod';

const AuthSchema = z.discriminatedUnion('mode', [
    z.object({id: z.literal('hawki'), mode: z.literal('credentials'), start_url: z.null(), last_error: z.string().nullable()}),
    z.object({id: z.literal('hawki'), mode: z.literal('redirect'), start_url: z.string().min(1), last_error: z.string().nullable()})
]);

export const LoginResponseSchema = z.object({meta: z.object({next: z.enum(['handshake', 'register'])})});
export const RegistrationPolicySchema = z.union([
    z.object({id: z.coerce.string().min(1), locale: z.string().min(1), text: z.string(), hash: z.string().min(1)}),
    z.object({policy: z.null(), consent: z.literal('valid')})
]);

export default AuthSchema;
export type Auth = z.infer<typeof AuthSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas { auth: Auth; }
}
