import z from 'zod';
import { BACKGROUNDS } from '$plugins/assistants/presets/backgrounds';
import { ReleaseMode, type Assistant, type AssistantAvatar } from '$plugins/assistants/types/assistant';
import type { UploadFile } from '$plugins/assistants/types/UploadFile';

/**
 * The `assistants` JSON:API resource, as it arrives from the backend, plus the
 * mapping into the {@link Assistant} shape the UI works with.
 *
 * ## Why a wire schema and not just `AssistantSchema`
 *
 * `RestApi` decodes responses with `jsona` using {@link JsonaPropertyMapper},
 * which does **no case conversion** — attributes and relationship keys land on
 * the decoded object exactly as `app/JsonApi/V1/Assistants/AssistantSchema.php`
 * spells them (`system_prompt`, `assistant_category`, ...). The SvelteKit app
 * this code came from used a camelizing deserializer, which is why the old
 * `assistantFromApi` could get away with a near-passthrough spread.
 *
 * On top of the renames, several fields are genuinely reshaped rather than
 * relabelled, so a snake_case mirror of `Assistant` would not be enough:
 *
 * | Wire                                   | Domain                          |
 * |----------------------------------------|---------------------------------|
 * | `assistant_user_prompts: [{text}]`     | `starterPrompts: string[]`      |
 * | `assistant_setting_values: [{value, setting:{key}}]` | `formality` / `answerLength` / `language` |
 * | `attachments: [{uuid, name, mime}]`    | `files: UploadFile[]`           |
 * | `_links: {edit: {meta:{message}}}`     | `actionPermissions: {edit: bool}` |
 * | `assistant_avatar: {icon_css}`         | `avatar: {iconCss}`             |
 *
 * Expressing that as a Zod `.transform()` means one declaration both validates
 * the response and produces the domain object — which is what the read half of
 * `assistantSerializer` used to do by hand, only unvalidated.
 *
 * ## Includes
 *
 * Every relationship is optional: it is only present when the request asked for
 * it via `include`. A missing relationship means "not loaded", so the transform
 * falls back to the same defaults `createEmptyAssistant()` uses rather than
 * inventing data.
 */

/** `{href, meta:{message: 'ALLOWED'|'DENIED'}}` action link, or a plain `self` URL string. */
const WireLinkSchema = z.union([
    z.string(),
    z.object({
        href: z.string().optional(),
        meta: z.object({
            message: z.string().optional(),
            method: z.string().optional()
        }).optional()
    })
]);

/** `users` resource, as included for `creator` / `remix_creator`. */
const WireUserSchema = z.object({
    id: z.string(),
    display_name: z.string().nullable().optional(),
    avatar: z.string().nullable().optional()
});

const WireCategorySchema = z.object({
    id: z.string(),
    text: z.string()
});

const WireTagSchema = z.object({
    id: z.string(),
    text: z.string()
});

const WireAvatarSchema = z.object({
    id: z.string(),
    name: z.string(),
    icon_css: z.string()
});

const WireUserPromptSchema = z.object({
    id: z.string(),
    text: z.string()
});

/** One saved setting value; `setting.key` is what maps it onto an assistant field. */
const WireSettingValueSchema = z.object({
    id: z.string(),
    value: z.string().nullable(),
    setting: z.object({
        id: z.string(),
        key: z.string()
    }).nullable().optional()
});

const WireVersionSchema = z.object({
    id: z.string(),
    text: z.string(),
    /** `Number::make('version')` on the backend, so this is numeric on the wire. */
    version: z.union([z.string(), z.number()]),
    /** `ArrayList::make('changed_keys')` — an array, not the comma-joined string the UI shows. */
    changed_keys: z.array(z.string()).nullable().optional(),
    created_at: z.string(),
    updated_at: z.string()
});

const WireAttachmentSchema = z.object({
    id: z.string(),
    uuid: z.string().nullable().optional(),
    name: z.string().nullable().optional(),
    mime: z.string().nullable().optional()
});

const WireFeedbackSchema = z.object({
    id: z.string(),
    text: z.string(),
    created_at: z.string(),
    user: WireUserSchema.nullable().optional()
});

/** Backend `assistant_setting_values[].setting.key` → the assistant field it fills. */
const SETTING_KEY_TO_FIELD = {
    formality: 'formality',
    language: 'language',
    answer_length: 'answerLength'
} as const satisfies Record<string, 'formality' | 'language' | 'answerLength'>;

/** The default avatar shown until the creator picks one. Mirrors `createEmptyAssistant()`. */
const FALLBACK_AVATAR: AssistantAvatar = {
    name: '📚',
    iconCss: BACKGROUNDS[0].value
};

/**
 * The decoded `assistants` resource, before mapping. Unknown keys are stripped
 * by Zod, which also drops `jsona`'s bookkeeping (`relationshipNames`) and any
 * attribute this frontend does not model yet.
 */
export const AssistantResourceSchema = z.object({
    id: z.string(),

    // Attributes — see AssistantSchema::fields() on the backend.
    name: z.string().nullable().optional(),
    handle: z.string().nullable().optional(),
    system_prompt: z.string().nullable().optional(),
    greeting: z.string().nullable().optional(),
    description: z.string().nullable().optional(),
    detail_description: z.string().nullable().optional(),
    allow_remix: z.boolean().nullable().optional(),
    allow_model_select: z.boolean().nullable().optional(),
    release_stage: z.enum(ReleaseMode).nullable().optional(),
    requested_release_stage: z.enum(ReleaseMode).nullable().optional(),
    model: z.string().nullable().optional(),
    max_tokens: z.number().nullable().optional(),
    temp: z.number().nullable().optional(),
    top_p: z.number().nullable().optional(),
    created_at: z.string().nullable().optional(),
    updated_at: z.string().nullable().optional(),
    /** Backed by a `withCount`, so it can arrive as `0`/`1` rather than a real boolean. */
    is_favorite: z.union([z.boolean(), z.number()]).nullable().optional(),

    // Relationships — present only when requested via `include`.
    assistant_category: WireCategorySchema.nullable().optional(),
    assistant_avatar: WireAvatarSchema.nullable().optional(),
    assistant_tags: z.array(WireTagSchema).nullable().optional(),
    assistant_user_prompts: z.array(WireUserPromptSchema).nullable().optional(),
    assistant_setting_values: z.array(WireSettingValueSchema).nullable().optional(),
    assistant_versions: z.array(WireVersionSchema).nullable().optional(),
    assistant_feedback: z.array(WireFeedbackSchema).nullable().optional(),
    attachments: z.array(WireAttachmentSchema).nullable().optional(),
    creator: WireUserSchema.nullable().optional(),
    remix_creator: WireUserSchema.nullable().optional(),
    /**
     * Self-referential (`maxDepth = 2` on the backend), so it is resolved
     * lazily and already mapped to the domain shape by the nested schema.
     *
     * `AssistantsSchema` carries an explicit `z.ZodType<Assistant>` annotation
     * for exactly this reason: without it TypeScript cannot close the loop
     * through a `.transform()` and collapses the whole schema to `any`.
     */
    get remixed_assistant() {
        return AssistantsSchema.nullable().optional();
    },
    ai_tools: z.array(z.any()).nullable().optional(),

    /** Per-resource action links; the source of {@link Assistant.actionPermissions}. */
    _links: z.record(z.string(), WireLinkSchema).optional()
});

export type AssistantResource = z.infer<typeof AssistantResourceSchema>;

/** `{update: {meta: {message: 'ALLOWED'}}}` → `{update: true}`. Plain string links (`self`) are skipped. */
function linksToPermissions(links: AssistantResource['_links']): Record<string, boolean> | null {
    if (!links) return null;

    const permissions: Record<string, boolean> = {};
    for (const [key, link] of Object.entries(links)) {
        if (typeof link === 'string') continue;
        permissions[key] = link.meta?.message === 'ALLOWED';
    }
    return permissions;
}

/** Pull one setting value out of the `assistant_setting_values` relationship by its backend key. */
function settingValue(
    values: AssistantResource['assistant_setting_values'],
    key: keyof typeof SETTING_KEY_TO_FIELD
): string | null {
    return values?.find(entry => entry.setting?.key === key)?.value ?? null;
}

/** Server attachments carry no size or timestamps, so those stay undefined and render empty. */
function toUploadFiles(attachments: AssistantResource['attachments']): UploadFile[] {
    if (!attachments?.length) return [];
    return attachments.map(attachment => ({
        uuid: attachment.uuid ?? undefined,
        name: attachment.name ?? '',
        mimeType: attachment.mime ?? undefined,
        status: 'complete' as const,
        progress: 100
    }));
}

/**
 * The registered `assistants` resource schema: validates the wire shape and
 * hands back a ready-to-use {@link Assistant}.
 *
 * Registered by `AssistantsPlugin.resourceSchemas()`, which is what makes
 * `app.restApi.getResource('assistants', id)` return a typed, validated
 * `Assistant` with no per-call mapping.
 */
const AssistantsSchema: z.ZodType<Assistant> = AssistantResourceSchema.transform((wire): Assistant => {
    const val=  {

        id: wire.id,

        name: wire.name ?? '',
        handle: wire.handle ?? null,
        systemPrompt: wire.system_prompt ?? '',
        greeting: wire.greeting ?? '',
        description: wire.description ?? '',
        detailDescription: wire.detail_description ?? '',
        allowRemix: wire.allow_remix ?? false,
        allowModelSelect: wire.allow_model_select ?? false,
        releaseStage: wire.release_stage ?? ReleaseMode.DRAFT,
        requested_release_stage: wire.requested_release_stage ?? null,

        // Not served by the backend yet — see the field docs on `Assistant`.
        riskLevel: null,
        riskNote: null,
        usageCount: null,

        starterPrompts: wire.assistant_user_prompts?.map(prompt => prompt.text) ?? [],

        model: wire.model ?? '',
        maxTokens: wire.max_tokens ?? 2048,
        temp: wire.temp ?? 0,
        topP: wire.top_p ?? 0,

        formality: settingValue(wire.assistant_setting_values, 'formality'),
        answerLength: settingValue(wire.assistant_setting_values, 'answer_length'),
        language: settingValue(wire.assistant_setting_values, 'language'),

        avatar: wire.assistant_avatar
            ? {
                id: wire.assistant_avatar.id,
                name: wire.assistant_avatar.name,
                iconCss: wire.assistant_avatar.icon_css
            }
            : FALLBACK_AVATAR,

        createdAt: wire.created_at ?? '',
        updatedAt: wire.updated_at ?? '',
        isFavorite: !!wire.is_favorite,

        category: wire.assistant_category ?? null,
        tags: wire.assistant_tags ?? [],
        creator: {
            id: wire.creator?.id ?? '',
            displayName: wire.creator?.display_name ?? '',
            avatar: wire.creator?.avatar ?? undefined
        },
        versions: wire.assistant_versions?.map(version => ({
            id: version.id,
            text: version.text,
            version: String(version.version),
            changedKeys: version.changed_keys?.join(', ') ?? null,
            createdAt: version.created_at,
            updatedAt: version.updated_at
        })) ?? [],

        remixCreator: wire.remix_creator
            ? {
                id: wire.remix_creator.id,
                displayName: wire.remix_creator.display_name ?? '',
                avatar: wire.remix_creator.avatar ?? undefined
            }
            : null,
        remixedAssistant: wire.remixed_assistant ?? null,

        files: toUploadFiles(wire.attachments),
        aiTools: wire.ai_tools ?? undefined,

        actionPermissions: linksToPermissions(wire._links),
        feedbacks: wire.assistant_feedback?.map(feedback => ({
            id: feedback.id,
            text: feedback.text,
            author: feedback.user?.display_name ?? '',
            createdAt: feedback.created_at
        }))
    }
    console.log('val', wire.creator)   ;
    return val;
});

export default AssistantsSchema;
