import type { AssistantTag } from "$plugins/assistants/types/assistant/AssistantTag";
import {AssistantSetting, AssistantSettingSchema} from "$lib/plugins/assistants/types/assistant/AssistantSetting";
import { useApp } from "$lib/app/hooks/useApp.svelte";
import {AssistantCategory} from "$plugins/assistants/types/assistant/AssistantCategory";

const ASSISTANT_CATEGORIES = "assistant-categories";
const ASSISTANT_TAGS = "assistant-tags";
export const ASSISTANT_SETTING_VALUES = "assistant-setting-values";
const ASSISTANT_SETTINGS = "assistant-settings";
export const SETTING_ANSWER_LENGTH = "answer_length";
export const SETTING_FORMALITY = "formality";
export const SETTING_LANGUAGE = "language";

export async function listCategories(): Promise<any[]> {
  try {
    const res = await useApp().restApi.getResourceCollection(ASSISTANT_CATEGORIES);
    console.log(res);
    return res;
  } catch (err) {
    throw err;
  }
}

export async function listTags(): Promise<any[]> {
  try {
      const res = await useApp().restApi.getResourceCollection(ASSISTANT_TAGS);
      console.log(res);
      return res;
  } catch (err) {
      throw err;
  }
}

export async function listSettings(): Promise<any[]> {
    console.log('start')
    try {
        const collection = await useApp().restApi.getResourceCollection(ASSISTANT_SETTINGS);
        console.log('settings', collection)
        return Array.from(collection);
    } catch (err) {
        // throw logApiError("listSettings", err);
        throw err;
    }
}

// export const makeDefaultAssistantSettingValues = async (
//   settings: ReadonlyArray<AssistantSetting>,
//   assistantId: string,
// ) => {
//
//   const defaultAssistantSettingValues = settings.map(
//     ({ id: settingId, defaultValue }) => ({
//       type: "assistant-setting-values",
//       attributes: {
//         value: defaultValue || "",
//       },
//       relationships: {
//         assistant: {
//           data: {
//             type: "assistants",
//             id: assistantId,
//           },
//         },
//         setting: {
//           data: {
//             type: "assistant-settings",
//             id: settingId,
//           },
//         },
//       },
//     }),
//   );
//   try {
//     await Promise.all(
//       defaultAssistantSettingValues.map((settingValue) =>
//         getApi().postResource(ASSISTANT_SETTING_VALUES, settingValue),
//       ),
//     );
//   } catch (err) {
//     // throw logApiError("makeDefaultAssistantSettingValues", err, { assistantId });
//       throw err;
//   }
// };

// export async function createTag(
//   assistantId: string,
//   text: string,
// ): Promise<AssistantTag> {
//   try {
//     const newTag = await getApi().postResource("assistant-tags", {
//       type: "assistant-tags",
//       attributes: {
//         text,
//       },
//     });
//     return toOption(newTag);
//   } catch (err) {
//     // throw logApiError("createTag", err, { assistantId });
//       throw err;
//   }
// }
