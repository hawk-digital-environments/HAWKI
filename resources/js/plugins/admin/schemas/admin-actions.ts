import z from 'zod';

export const ProviderDiscoverySchema = z.object({
    models: z.array(z.object({ model_id: z.string(), label: z.string() }))
});
export type ProviderDiscovery = z.infer<typeof ProviderDiscoverySchema>;

export const ModelInspectionSchema = z.object({ model: z.record(z.string(), z.unknown()) });
export const McpDiscoverySchema = z.object({ tools: z.array(z.string()) });
export type McpDiscovery = z.infer<typeof McpDiscoverySchema>;

export const UserTokensSchema = z.object({
    tokens: z.array(
        z.object({
            id: z.number(),
            name: z.string(),
            created_at: z.string().nullable(),
            last_used_at: z.string().nullable(),
            expires_at: z.string().nullable()
        })
    )
});
export type UserTokens = z.infer<typeof UserTokensSchema>;

export const QueuedActionSchema = z.object({ queued: z.literal(true) });
export const McpTestSchema = z.object({ online: z.literal(true) });
export const ModelRefreshSchema = z.object({ refreshed: z.literal(true) });
export const ModelStatusCheckSchema = z.object({ checked: z.literal(true) });
export const RevokeTokensSchema = z.object({ revoked: z.number().int().nonnegative() });
