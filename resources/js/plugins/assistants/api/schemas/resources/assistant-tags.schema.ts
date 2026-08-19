import { AssistantTagSchema } from '$plugins/assistants/types/assistant/AssistantTag';

/**
 * The `assistant-tags` JSON:API resource. Registered by
 * `AssistantsPlugin.resourceSchemas()` for `RestApi.getResource(Collection)`.
 *
 * Wire and domain shape are identical here (`{id, text}`), so this is the
 * domain schema itself rather than a separate wire schema + transform — see
 * `assistants.schema.ts` for what that looks like when they diverge.
 */
export default AssistantTagSchema;

/**
 * Unlike categories, tags are user-creatable (free text). Not wired up yet —
 * `assistantOptionsClient.ts`'s `createTag` is still commented out — but this
 * is where the request body goes once it is.
 */
export function tagToApi(text: string) {
    return {
        type: 'assistant-tags',
        attributes: { text }
    };
}
