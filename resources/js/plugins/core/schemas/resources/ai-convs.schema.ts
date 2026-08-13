import z from 'zod';

/**
 * Validates the `ai-convs` JSON:API resource — the metadata of the current
 * user's private AI conversations, used to populate the chat history sidebar.
 *
 * The resource intentionally only carries metadata: the encrypted message
 * bodies stay behind the legacy single-conversation endpoint so listings never
 * download chat histories they do not display.
 *
 * Registers the resource under the key `'ai-convs'` in `HawkiResourceSchemas`
 * (see the `declare module` augmentation below).
 */
const AiConvSchema = z.object({
    id: z.string(),
    name: z.string(),
    slug: z.string(),
    created_at: z.string().nullable(),
    updated_at: z.string().nullable()
});

export default AiConvSchema;

export type AiConv = z.infer<typeof AiConvSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'ai-convs': AiConv;
    }
}
