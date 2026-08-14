import type { Category } from "$lib/plugins/assistants/types/assistant/Category";
import type { Tag } from "$lib/plugins/assistants/types/assistant/Tag";
import type { AssistantSetting } from "$lib/plugins/assistants/types/assistant/AssistantSetting";
import { useApp } from "$lib/app/hooks/useApp.svelte";

const CATEGORIES = "assistant-categories";
const ASSISTANT_TAGS = "assistant-tags";
export const ASSISTANT_SETTING_VALUES = "assistant-setting-values";
const SETTINGS = "assistant-settings";
export const SETTING_ANSWER_LENGTH = "answer_length";
export const SETTING_FORMALITY = "formality";
export const SETTING_LANGUAGE = "language";

export async function listCategories(): Promise<Category[]> {
  try {
    const collection = await useApp().restApi.getResourceCollection(CATEGORIES);
    const asd = collection.getAll().map(toOption);
    debugger;
    return asd;
  } catch (err) {
    throw err;
  }
}

export async function listTags(): Promise<Tag[]> {
  try {
    const collection = await getApi().getCollectionPage(ASSISTANT_TAGS);
    return collection.getAll().map(toOption);
  } catch (err) {
    throw logApiError("listTags", err);
  }
}

export async function listSettings(): Promise<AssistantSetting[]> {
  try {
    const collection = await getApi().getCollectionPage(SETTINGS);
    return collection.response.data.map(toSetting);
  } catch (err) {
    throw logApiError("listSettings", err);
  }
}

export const makeDefaultAssistantSettingValues = async (
  settings: ReadonlyArray<AssistantSetting>,
  assistantId: string,
) => {
  // Frontend-only mode: the setting values live on the mock draft itself,
  // so there is nothing to POST.
  if (useMockData) return;

  const defaultAssistantSettingValues = settings.map(
    ({ id: settingId, defaultValue }) => ({
      type: "assistant-setting-values",
      attributes: {
        value: defaultValue || "",
      },
      relationships: {
        assistant: {
          data: {
            type: "assistants",
            id: assistantId,
          },
        },
        setting: {
          data: {
            type: "assistant-settings",
            id: settingId,
          },
        },
      },
    }),
  );
  try {
    await Promise.all(
      defaultAssistantSettingValues.map((settingValue) =>
        getApi().postResource(ASSISTANT_SETTING_VALUES, settingValue),
      ),
    );
  } catch (err) {
    throw logApiError("makeDefaultAssistantSettingValues", err, { assistantId });
  }
};

export async function createTag(
  assistantId: string,
  text: string,
): Promise<Tag> {
  try {
    const newTag = await getApi().postResource("assistant-tags", {
      type: "assistant-tags",
      attributes: {
        text,
      },
    });
    return toOption(newTag);
  } catch (err) {
    throw logApiError("createTag", err, { assistantId });
  }
}

function toOption(resource: Resource): { id: string; text: string } {
  return {
    id: String(resource.get("id")),
    text: String(
      resource.get("text") ??
        resource.get("name") ??
        resource.get("label") ??
        "",
    ),
  };
}

function toSetting(data: any): AssistantSetting {
  return {
    id: data.id,
    key: data.key,
    label: data.label,
    description: data.description,
    options: data.uiOptions,
    defaultValue: data.defaultValue ?? null,
  };
}
