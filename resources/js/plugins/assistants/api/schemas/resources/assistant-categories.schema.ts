import { AssistantCategorySchema } from '$plugins/assistants/types/assistant/AssistantCategory';

/**
 * The `assistant-categories` JSON:API resource. Registered by
 * `AssistantsPlugin.resourceSchemas()` for `RestApi.getResource(Collection)`.
 *
 * Wire and domain shape are identical here (`{id, text}`), so this is the
 * domain schema itself rather than a separate wire schema + transform — see
 * `assistants.schema.ts` for what that looks like when they diverge.
 *
 * No `toApi`: categories are server-defined and not user-creatable (see the
 * doc comment on {@link AssistantCategorySchema}).
 */
export default AssistantCategorySchema;
