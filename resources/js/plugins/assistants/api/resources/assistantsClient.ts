import { useApp } from "$lib/app/hooks/useApp.svelte.js";
import type { FetchCollectionQuery, FetchResourceQuery } from "$lib/kernel/api/buildQueryString.js";
import type { JsonApiPagination } from "$lib/kernel/api/jsonApiEncoding.js";
import { logApiError } from "$plugins/assistants/api/errors";
import type { Assistant } from "$plugins/assistants/types/assistant";
import {
  assistantToApi,
  AssistantResourceSchema,
  ASSISTANT_SETTING_KEY_MAP,
} from "$plugins/assistants/api/schemas/resources/assistants.schema.js";
import type { JsonApiResourceBody } from "$plugins/assistants/api/schemas/wireFragments.js";
import { assistantOptionsStore } from "$plugins/assistants/stores/AssistantOptionsStore.svelte.js";

const ASSISTANTS = "assistants";
const ASSISTANT_USER_PROMPTS = "assistant-user-prompts";
const ASSISTANT_SETTING_VALUES = "assistant-setting-values";

/**
 * Relationships worth loading for a list view: enough to render a card without
 * a follow-up request. Callers can add more via `include`.
 *
 * These names are the JSON:API relationship names declared in
 * `app/JsonApi/V1/Assistants/AssistantSchema.php` — NOT the camelCase field
 * names on {@link Assistant}.
 */
export const ASSISTANT_LIST_INCLUDES = [
  "creator",
  "assistant_category",
  "assistant_avatar",
  "remixed_assistant",
] as const;

/**
 * Everything the detail page renders unconditionally. `assistant_feedback` is
 * deliberately NOT here: it's a permission-gated relationship
 * (`viewAssistantFeedback` in the resource's `links`), and requesting a
 * denied include rejects the *entire* request with a 403 — not just that one
 * relationship. It's fetched separately, only after the initial response
 * confirms the permission is granted (see `detail/page.svelte`).
 */
export const ASSISTANT_DETAIL_INCLUDES = [
  "creator",
  "assistant_category",
  "assistant_tags",
  "assistant_avatar",
  "assistant_versions"
] as const;

/** {@link ASSISTANT_DETAIL_INCLUDES} plus the owner-only knowledge files. */
export const ASSISTANT_EDIT_INCLUDES = [
  ...ASSISTANT_DETAIL_INCLUDES,
    "attachments",
    "assistant_user_prompts",
    "assistant_setting_values.setting",
] as const;

/** One page of assistants plus the server's paging metadata. */
export interface AssistantPage {
  assistants: Assistant[];
  /** `undefined` when the response carried no `meta.page` block. */
  pagination?: JsonApiPagination;
}

/**
 * SvelteKit `load` dependency key for a single assistant.
 *
 * The detail route declares it via `depends()`, so a mutation made elsewhere in
 * the app (e.g. the favourite toggle on an assistant card) can `invalidate()`
 * it. Invalidating additionally discards SvelteKit's preload cache — with
 * `data-sveltekit-preload-data="hover"` the detail `load` already ran while the
 * pointer was over the card, i.e. *before* the toggle, and the click would
 * otherwise reuse that stale result instead of refetching.
 */
export function assistantDependency(id: string): `${string}:${string}` {
  return `hawki:assistant:${id}`;
}

/**
 * Fetch one assistant by id.
 *
 * The whole read path is: `RestApi.getResource` builds the URL and query string
 * → `decodeJsonApiResourceResponse` flattens the JSON:API envelope with `jsona`
 * (attributes and relationship keys stay **snake_case**; includes become nested
 * objects) → {@link AssistantsSchema} validates that wire shape and maps it to
 * the camelCase {@link Assistant}. Nothing else in the app needs to know the
 * wire format.
 *
 * @example
 * const assistant = await getAssistant(id, { include: ASSISTANT_DETAIL_INCLUDES });
 * assistant.systemPrompt;        // mapped from `system_prompt`
 * assistant.actionPermissions;   // derived from the resource's `_links`
 */
export async function getAssistant(
  id: string,
  query?: FetchResourceQuery,
): Promise<Assistant> {
  try {
    return await useApp().restApi.getResource(ASSISTANTS,
        id,
        {
            query
        });
  } catch (err) {
    throw logApiError("getAssistant", err, { id });
  }
}

/**
 * Fetch a page of assistants.
 *
 * Note the collection shape changed with the new kernel: `getResourceCollection`
 * resolves to a plain **array** carrying extra properties, not a wrapper object.
 * There is no `.getAll()` and no `.pagination` — the items *are* the array and
 * paging lives on `_pagination` (`{page, pages, pageSize, itemCount,
 * hasNextPage, hasPreviousPage}`), put there by `extendResourceCollection` from
 * the response's `meta.page` and `links`.
 *
 * This returns {@link AssistantPage} rather than the raw collection so callers
 * do not have to know that an array can carry metadata.
 *
 * @example
 * const {assistants, pagination} = await listAssistants({
 *     include: ASSISTANT_LIST_INCLUDES,
 *     filter: {name: 'research'},
 *     page: {number: 2, size: 20},
 * });
 */
export async function listAssistants(
  query?: FetchCollectionQuery,
): Promise<AssistantPage> {
  try {
      const collection = await useApp().restApi.getResourceCollection(ASSISTANTS, {
        query
      })

      return {
          assistants: Array.from(collection),
          pagination: collection._pagination,
      };
  } catch (err) {
    throw err
  }
}

/**
 * Mints a new assistant record. `restApi.createResource` sends whatever
 * object it's given straight through as `data.attributes` with no shaping —
 * it must already be wire-shaped (snake_case, no relationships/read-only
 * fields), which is exactly what {@link assistantToApi} produces. Passing the
 * raw domain `Assistant` here previously sent camelCase field names and
 * nested domain objects (`avatar`, `creator`, `versions`, ...) as attributes,
 * which the backend rejected with a 400 pointing at `/data/attributes`.
 *
 * `assistantToApi`'s `relationships` half is dropped: `createResource` has no
 * way to send them, and a brand-new draft has nothing to relate yet anyway
 * (`createEmptyAssistant()`'s `category`/`aiTools` are empty/null, and its
 * `tags: []` relationship would be a no-op even if it could be sent).
 */
export async function createAssistant(
  assistantResource: Assistant,
): Promise<Assistant> {
  try {
    return await useApp().restApi.createResource(
        ASSISTANTS,
        assistantToApi(assistantResource).attributes ?? {}
    );
  } catch (err) {
    throw logApiError("createAssistant", err);
  }
}

/**
 * Persists a partial (or full) change to an existing assistant. `body` is
 * whatever {@link assistantToApi} produced — `attributes` and `relationships`
 * are sent in the same PATCH so a single autosave cycle can update scalar
 * fields (`name`, `temp`, ...) and relationships (`assistant_category`,
 * `assistant_tags`, `ai_tools`) together.
 */
export async function updateAssistant(
  id: string,
  body: JsonApiResourceBody,
): Promise<Assistant> {
  try {
    return await useApp().restApi.updateResource(ASSISTANTS, id, body.attributes ?? {}, {
      relationships: body.relationships,
    });
  } catch (err) {
    throw logApiError("updateAssistant", err, { id });
  }
}

/**
 * Requests a release stage via the dedicated `actions/release` endpoint —
 * `release_stage` is `readOnly()` on the main resource (see
 * `ReleaseAssistantRequest::rules()`, which validates exactly
 * `data.attributes.release_stage`), so it can't be sent through
 * {@link updateAssistant}. `postToResourceAction` doesn't decode the
 * response, but nothing here needs it: a non-2xx status throws (see
 * `RestApi.fetch`'s doc comment), so resolving at all means success.
 */
export async function requestAssistantRelease(assistant: Assistant): Promise<boolean> {
  if (!assistant.id) return false;
  try {
    await useApp().restApi.postToResourceAction(
      ASSISTANTS,
      `${assistant.id}/actions/release`,
      {
        data: {
          type: "assistants",
          attributes: {
            release_stage: assistant.releaseStage,
          },
        },
      },
    );
    return true;
  } catch (err) {
    throw logApiError("requestAssistantRelease", err, { id: assistant.id });
  }
}

/**
 * Remixes an assistant into a fresh, personally-owned draft via the
 * `actions/remix` endpoint. The action response isn't JSON:API-decoded (see
 * {@link RestApi.postToResourceAction}), so this only reads the new
 * resource's raw `data.id` off it and refetches the full, mapped assistant
 * through {@link getAssistant} — mirroring the fetch-then-map shape every
 * other read in this file already goes through.
 */
export async function requestRemix(id: string): Promise<Assistant> {
  try {
    const response = await useApp().restApi.postToResourceAction(
      ASSISTANTS,
      `${id}/actions/remix`,
      { data: {} },
    );
    return await getAssistant(response.data.id, {
      include: [...ASSISTANT_EDIT_INCLUDES],
    });
  } catch (err) {
    throw logApiError("requestRemix", err, { id });
  }
}


/**
 * Sets one setting value (formality / language / answer length) on an
 * assistant. `assistant-setting-values` is a separate resource keyed by
 * `(assistant, setting)` with a uniqueness constraint enforced server-side
 * (see `AssistantSettingValueRequest::rules()`), so this is an upsert: PATCH
 * the existing row's `value` if one exists for this key, otherwise POST a new
 * row with both relationships.
 *
 * The domain `Assistant` only ever carries the *flattened* value
 * (`formality`/`answerLength`/`language`) — `assistants.schema.ts`'s
 * transform consumes `assistant_setting_values` to produce it and discards
 * the row ids. Finding the existing row therefore needs a fresh, *unmapped*
 * fetch — `getResource(..., {validateSchema: false})` returns the raw wire
 * response, which {@link AssistantResourceSchema} (the pre-transform half of
 * that same schema) can validate on its own without running the transform.
 */
export async function updateAssistantSetting(
  id: string,
  settingKey: "formality" | "language" | "answerLength",
  settingValue: string,
): Promise<void> {
  try {
    const apiKey = ASSISTANT_SETTING_KEY_MAP[settingKey];
    if (!apiKey) return;

    const setting = assistantOptionsStore.settings.find((s) => s.key === apiKey);
    if (!setting) {
      throw new Error(`Unknown assistant setting "${apiKey}" (has assistantOptionsStore.load() run?)`);
    }

    const raw = await useApp().restApi.getResource(ASSISTANTS, id, {
      query: { include: "assistant_setting_values.setting" },
      validateSchema: false,
    });
    const wire = AssistantResourceSchema.parse(raw);
    const existing = wire.assistant_setting_values?.find(
      (sv) => sv.setting?.key === apiKey,
    );

    if (existing) {
      await useApp().restApi.updateResource(ASSISTANT_SETTING_VALUES, existing.id, {
        value: settingValue,
      });
    } else {
      await useApp().restApi.createResource(ASSISTANT_SETTING_VALUES, {
        value: settingValue,
      }, {
        relationships: {
          assistant: { data: { type: "assistants", id } },
          setting: { data: { type: "assistant-settings", id: setting.id } },
        },
      });
    }
  } catch (err) {
    throw logApiError("updateAssistantSetting", err, { id, settingKey });
  }
}

export async function createAssistantPrompts(
  id: string,
  promptsAdded: ReadonlyArray<string>,
): Promise<void> {
  try {
    await Promise.all(
      promptsAdded.map((text) =>
        useApp().restApi.createResource(ASSISTANT_USER_PROMPTS, { text }, {
          relationships: {
            assistant: { data: { type: "assistants", id } },
          },
        }),
      ),
    );
  } catch (err) {
    throw logApiError("createAssistantPrompts", err, { id });
  }
}

/**
 * Prompts are tracked by their text (see `Assistant.starterPrompts:
 * string[]`), not by id, so removal needs the same raw-wire lookup as
 * {@link updateAssistantSetting} to resolve which `assistant-user-prompts`
 * rows to delete.
 */
export async function removeAssistantPrompts(
  id: string,
  promptsRemoved: ReadonlyArray<string>,
): Promise<void> {
  try {
    const raw = await useApp().restApi.getResource(ASSISTANTS, id, {
      query: { include: "assistant_user_prompts" },
      validateSchema: false,
    });
    const wire = AssistantResourceSchema.parse(raw);
    const promptsToRemoveSet = new Set(promptsRemoved);
    const toRemove = (wire.assistant_user_prompts ?? []).filter((p) =>
      promptsToRemoveSet.has(p.text),
    );
    await Promise.all(
      toRemove.map((p) => useApp().restApi.deleteResource(ASSISTANT_USER_PROMPTS, p.id)),
    );
  } catch (err) {
    throw logApiError("removeAssistantPrompts", err, { id });
  }
}


export async function toggleAssistantFavorite(
    assistant: Assistant,
    active: boolean,
): Promise<void> {
  if (!assistant.id) return;
  const action = `${assistant.id}/actions/favorite`;
  try {
    if (active) {
      await useApp().restApi.postToResourceAction(ASSISTANTS, action, { data: {} });
    } else {
      await useApp().restApi.deleteFromResourceAction(ASSISTANTS, action);
    }
  } catch (err) {
    throw logApiError("toggleAssistantFavorite", err, {
      id: assistant.id,
      active,
    });
  }
}
