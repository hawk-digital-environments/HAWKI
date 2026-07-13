import type { AssistantTag } from "$plugins/assistants/types/assistant/AssistantTag";
import type { AssistantCategory } from "$plugins/assistants/types/assistant/AssistantCategory";
import type { AssistantSetting } from "$plugins/assistants/types/assistant/AssistantSetting";
import { useApp } from "$lib/app/hooks/useApp.svelte";
import { logApiError } from "$plugins/assistants/api/errors";
import { tagToApi } from "$plugins/assistants/api/schemas/resources/assistant-tags.schema";

const ASSISTANT_CATEGORIES = "assistant-categories";
const ASSISTANT_TAGS = "assistant-tags";
const ASSISTANT_SETTINGS = "assistant-settings";

export async function listCategories(): Promise<AssistantCategory[]> {
  try {
    // `getResourceCollection`'s untyped-string overload types its result as
    // `JsonApiCollection<any[]>`, an artifact of the kernel generic (not
    // meaningfully `any[]` per element) — the registered `assistant-categories`
    // schema already maps each entry to a real `AssistantCategory` at runtime.
    return Array.from(await useApp().restApi.getResourceCollection(ASSISTANT_CATEGORIES)) as unknown as AssistantCategory[];
  } catch (err) {
    throw logApiError("listCategories", err);
  }
}

export async function listTags(): Promise<AssistantTag[]> {
  try {
    return Array.from(await useApp().restApi.getResourceCollection(ASSISTANT_TAGS)) as unknown as AssistantTag[];
  } catch (err) {
    throw logApiError("listTags", err);
  }
}

export async function listSettings(): Promise<AssistantSetting[]> {
  try {
    return Array.from(await useApp().restApi.getResourceCollection(ASSISTANT_SETTINGS)) as unknown as AssistantSetting[];
  } catch (err) {
    throw logApiError("listSettings", err);
  }
}

/**
 * Create a new, user-defined tag (backend enforces uniqueness on `text`).
 * Tags aren't scoped to an assistant (see `AssistantTagRequest::rules()` —
 * only `text` is validated), so nothing here takes an assistant id.
 */
export async function createTag(text: string): Promise<AssistantTag> {
  try {
    const body = tagToApi(text);
    return await useApp().restApi.createResource(ASSISTANT_TAGS, body.attributes);
  } catch (err) {
    throw logApiError("createTag", err, { text });
  }
}
