/**
 * Display labels for the review payload's changed keys: raw payload keys
 * (attribute names plus the relation keys the version service diffs) mapped
 * to their i18n label keys. Unknown keys fall back to a humanized form, so a
 * newly added field never renders as a raw snake_case key.
 */
const FIELD_LABEL_KEYS: Record<string, string> = {
    name: 'assistants.admin.detail.fields.name',
    handle: 'assistants.admin.detail.fields.handle',
    description: 'assistants.admin.detail.fields.description',
    detail_description: 'assistants.admin.detail.fields.detail_description',
    greeting: 'assistants.admin.detail.fields.greeting',
    system_prompt: 'assistants.admin.detail.fields.system_prompt',
    model: 'assistants.admin.detail.fields.model',
    max_tokens: 'assistants.admin.detail.fields.max_tokens',
    temp: 'assistants.admin.detail.fields.temp',
    top_p: 'assistants.admin.detail.fields.top_p',
    allow_remix: 'assistants.admin.detail.fields.allow_remix',
    allow_model_select: 'assistants.admin.detail.fields.allow_model_select',
    category_id: 'assistants.admin.detail.fields.category',
    assistant_tags: 'assistants.admin.detail.fields.tags',
    tag_ids: 'assistants.admin.detail.fields.tags',
    assistant_user_prompts: 'assistants.admin.detail.fields.prompts',
    user_prompts: 'assistants.admin.detail.fields.prompts',
    ai_tools: 'assistants.admin.detail.fields.tools',
    tool_ids: 'assistants.admin.detail.fields.tools',
    attachment_ids: 'assistants.admin.detail.fields.attachments',
    setting_values: 'assistants.admin.detail.fields.settings',
    avatar_id: 'assistants.admin.detail.fields.avatar'
};

export type Translate = (key: string) => string;

/** Underscores to spaces — the fallback shape for unmapped keys. */
export function humanizeKey(key: string): string {
    return key.replaceAll('_', ' ');
}

/** The display label for a changed key: translated when known, humanized otherwise. */
export function fieldLabel(key: string, translate: Translate): string {
    const labelKey = FIELD_LABEL_KEYS[key];

    return labelKey !== undefined ? translate(labelKey) : humanizeKey(key);
}
