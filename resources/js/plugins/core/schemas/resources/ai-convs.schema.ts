import z from 'zod';
import AiConvMessageSchema from '$plugins/core/schemas/resources/ai-conv-messages.schema.js';

/**
 * Validates the `ai-convs` JSON:API resource — the current user's private AI
 * conversations. The slug doubles as the resource id.
 *
 * Listings only carry the conversation metadata; the encrypted messages are
 * only present when a single conversation is fetched with
 * `?include=messages`, so listings never download chat histories they do not
 * display.
 *
 * Registers the resource under the key `'ai-convs'` in `HawkiResourceSchemas`
 * (see the `declare module` augmentation below).
 */
const AiConvSchema = z.object({
    id: z.string(),
    name: z.string(),
    slug: z.string(),
    system_prompt: z.string().nullable().optional(),
    assistant_handle: z.string().nullable().optional(),
    created_at: z.string().nullable(),
    updated_at: z.string().nullable(),
    messages: z.array(AiConvMessageSchema).optional()
});

export default AiConvSchema;

export type AiConv = z.infer<typeof AiConvSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'ai-convs': AiConv;
    }
}
