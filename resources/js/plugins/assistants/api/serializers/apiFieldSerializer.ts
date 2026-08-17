import type {Assistant} from "$lib/plugins/assistants/types/assistant/Assistant";

/**
 * Maps an {@link Assistant} option field to its `key` in the options store.
 * This is the single source of truth for which assistant fields are persisted
 * as `settings` (via {@link optionToApi}) rather than plain attributes.
 */
export const ASSISTANT_SETTING_KEY_MAP: Partial<
    Pick<Assistant, "formality" | "language" | "answerLength">
> = {
    formality: "formality",
    language: "language",
    answerLength: "answer_length",
};

/** The `Assistant` fields that are persisted as setting values. */
export const ASSISTANT_SETTING_KEYS = Object.keys(
    ASSISTANT_SETTING_KEY_MAP,
) as (keyof Assistant)[];

/**
 * Inverse of the write mapping in {@link assistantToApi}: maps a JSON:API field
 * name (the snake_case attribute/relationship name the server reports in an
 * error pointer) back to its `Assistant` key. Used to route server validation
 * errors to the right field. Keep in sync with `assistantToApi` / `optionToApi`.
 */
const API_FIELD_TO_KEY: Record<string, keyof Assistant> = {
    name: "name",
    handle: "handle",
    system_prompt: "systemPrompt",
    greeting: "greeting",
    description: "description",
    detail_description: "detailDescription",
    allow_remix: "allowRemix",
    allow_model_select: "allowModelSelect",
    release_stage: "releaseStage",
    model: "model",
    max_tokens: "maxTokens",
    temp: "temp",
    top_p: "topP",
    avatar: "avatar",
    is_favorite: "isFavorite",
    // relationships
    assistant_category: "category",
    tags: "tags",
    ai_tools: "aiTools",
    // settings options
    formality: "formality",
    language: "language",
    answer_length: "answerLength",
    // starter prompts (sent via the user-prompts add/remove endpoint)
    add: "starterPrompts",
    remove: "starterPrompts",
};

/** Map a JSON:API error field name to its `Assistant` key, if known. */
export function apiFieldToAssistantKey(
    field?: string,
): keyof Assistant | undefined {
    return field ? API_FIELD_TO_KEY[field] : undefined;
}
