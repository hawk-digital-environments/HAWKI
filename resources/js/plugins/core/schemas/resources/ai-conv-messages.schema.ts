import z from 'zod';
import UsersSchema from '$plugins/core/schemas/resources/users.schema.js';

/**
 * Validates the `ai-conv-messages` JSON:API resource — a single message of a
 * private AI conversation. It is returned when a conversation is fetched with
 * `?include=messages` and by the message actions on the `ai-convs` resource.
 *
 * `content` is the encrypted message body in the portable
 * `base64(iv)|base64(tag)|base64(ciphertext)` string format (see
 * `loadSymmetricCryptoValue`); decryption happens client-side in the chat
 * store. Attachments are a JSON:API relationship, so requests must ask for
 * them explicitly (`include=messages.attachments` / `include=attachments`);
 * the files themselves are served through the storage proxy using the
 * `identifier` of each attachment resource.
 *
 * Registers the resource under the key `'ai-conv-messages'` in
 * `HawkiResourceSchemas` (see the `declare module` augmentation below).
 */
const AiConvMessageSchema = z.object({
    id: z.string(),
    message_id: z.string(),
    message_role: z.enum(['user', 'assistant']),
    model: z.string().nullable(),
    completion: z.boolean(),
    metadata: z.record(z.string(), z.any()).nullable(),
    content: z.string(),
    author: UsersSchema.pick({
        id: true,
        name: true,
        username: true,
        avatar: true
    }),
    attachments: z.array(z.object({
        uuid: z.string(),
        name: z.string(),
        category: z.string(),
        type: z.string(),
        mime: z.string(),
        identifier: z.string()
    })),
    created_at: z.string().nullable(),
    updated_at: z.string().nullable()
});

export default AiConvMessageSchema;

export type AiConvMessage = z.infer<typeof AiConvMessageSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'ai-conv-messages': AiConvMessage;
    }
}
