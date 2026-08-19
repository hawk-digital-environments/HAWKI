import z from "zod";
import { useApp } from "$lib/app/hooks/useApp.svelte.js";
import type { FetchCollectionQuery, FetchResourceQuery } from "$lib/kernel/api/buildQueryString.js";
import type { JsonApiCollection, JsonApiPagination } from "$lib/kernel/api/jsonApiEncoding.js";
import { logApiError } from "$plugins/assistants/api/errors";
import type { Assistant } from "$plugins/assistants/types/assistant";
// import {ASSISTANT_SETTING_KEY_MAP} from "$plugins/assistants/api/schemas/resources/assistants.schema"
// import { ASSISTANT_SETTING_VALUES } from "./assistantOptionsClient";
// import lodash from "lodash";

const ASSISTANTS = "assistants";
const ASSISTANT_USER_PROMPTS = "assistant-user-prompts";

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

/** Everything the detail page renders. */
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

// export async function createAssistant(
//   assistantResource: JsonApiResourceBody,
// ): Promise<Assistant> {
  // try {
  //   const resource = await  useApp().restApi.postResource(
  //     TYPE,
  //     lodash.omit(assistantResource, "id"),
  //   );
  //   return assistantFromApi(resource);
  // } catch (err) {
  //   throw logApiError("createAssistant", err);
  // }
// }

// export async function updateAssistant(
//   id: string,
//   body: JsonApiResourceBody,
// ): Promise<void> {
  // try {
  //   const response = await  useApp().restApi.patchResource(TYPE, id, body);
  //   console.log(response);
  // } catch (err) {
  //   throw logApiError("updateAssistant", err, { id });
  // }
// }
// /${assistant.id}/actions/release
// export async function requestAssistantRelease(assistant: Assistant): Promise<boolean> {
//   const response = await  useApp().restApi.postToResourceAction(`${TYPE}`,'actions/release',
//       {
//         data:{
//           "attributes": {
//             "release_stage": assistant.releaseStage,
//           }
//         }
//       });
//   return response.status == 200;
// }


// export async function requestRemix (
//     id: string
// ){
  // try{
  //   const response =  await  useApp().restApi.postToResourceAction( 'assistants',  'actions/remix', {})
  //   const detailedData = await getAssistant(response.data.data.id, {
  //     include: [
  //         'creator',
  //         'assistant_category',
  //         'assistant_tags',
  //         'assistant_avatar',
  //         'assistant_setting_values.setting',
  //         'assistant_user_prompts',
  //         'assistant_versions',
  //     ],
  //   });
  //   return detailedData;
  // } catch(err){
  //   throw err;
  // }
// }


export async function updateAssistantSetting(
  id: string,
  settingKey: "formality" | "language" | "answerLength",
  settingValue: string,
): Promise<void> {
  // try {
  //   const apiKeyLookup = ASSISTANT_SETTING_KEY_MAP[settingKey];
  //   const assistant = await  useApp().restApi.getResource(TYPE, id,
  //       {
  //           include: "assistant_setting_values.setting",
  //       });
  //   const assistantSettingValue = assistant
  //     .get("assistantSettingValues")
  //     .find((sVal: any) => sVal["setting"]["key"] === apiKeyLookup);
  //   await  useApp().restApi.patchResource(
  //     ASSISTANT_SETTING_VALUES,
  //     assistantSettingValue["id"],
  //     {
  //       type: "assistant-setting-values",
  //       id: assistantSettingValue["id"],
  //       attributes: {
  //         value: settingValue,
  //       },
  //       relationships: {
  //         assistant: {
  //           data: {
  //             type: "assistants",
  //             id: id,
  //           },
  //         },
  //         setting: {
  //           data: {
  //             type: "assistant-settings",
  //             id: assistantSettingValue["setting"]["id"],
  //           },
  //         },
  //       },
  //     },
  //   );
  // } catch (err) {
  //   throw logApiError("updateAssistantSetting", err, { id, settingKey });
  // }
}
export async function createAssistantPrompts(
  id: string,
  promptsAdded: ReadonlyArray<string>,
): Promise<void> {
  // try {
  //   await Promise.all(
  //     promptsAdded.map((text) =>
  //        useApp().restApi.postResource(ASSISTANT_USER_PROMPTS, {
  //         type: ASSISTANT_USER_PROMPTS,
  //         attributes: {
  //           text,
  //         },
  //         relationships: {
  //           assistant: {
  //             data: {
  //               type: "assistants",
  //               id,
  //             },
  //           },
  //         },
  //       }),
  //     ),
  //   );
  // } catch (err) {
  //   throw logApiError("createAssistantPrompts", err, { id });
  // }
}

export async function removeAssistantPrompts(
  id: string,
  promptsRemoved: ReadonlyArray<string>,
): Promise<void> {
  // try {
  //   const assistant = await  useApp().restApi.getResource(TYPE, id, {
  //     include: "assistant_user_prompts",
  //   });
  //   const promptsToRemoveSet = new Set(promptsRemoved);
  //   const toRemove = assistant
  //     .get("assistantUserPrompts")
  //     .filter(({ text }: { text: string }) => promptsToRemoveSet.has(text));
  //   await Promise.all(
  //     toRemove.map(({ id }: { id: string }) =>
  //        useApp().restApi.axios.delete(`${ASSISTANT_USER_PROMPTS}/${id}`),
  //     ),
  //   );
  // } catch (err) {
  //   throw logApiError("removeAssistantPrompts", err, { id });
  // }
}


export async function toggleAssistantFavorite(
    assistant: Assistant,
    active: boolean,
): Promise<void> {
  const method = active ? 'post' : 'delete';
  // try {
  //   await  useApp().restApi.axios[method](`${TYPE}/${assistant.id}/actions/favorite`);
  // } catch (err) {
  //   throw logApiError("toggleAssistantFavorite", err, {
  //     id: assistant.id,
  //     active,
  //   });
  // }
}
