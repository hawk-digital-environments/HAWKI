import z from 'zod';

export const LogoutResponseSchema = z.object({meta: z.object({redirect_url: z.string().nullable()})});
