import z from 'zod';
import { BACKGROUNDS } from '$lib/plugins/assistants/presets/backgrounds';
import { ReleaseMode, type Assistant, type AssistantAvatar, type AssistantKey } from '$plugins/assistants/types/assistant';
import { AssistantCategorySchema } from '$plugins/assistants/types/assistant/AssistantCategory';
import { AssistantTagSchema } from '$plugins/assistants/types/assistant/AssistantTag';
import type { UploadFile } from '$plugins/assistants/types/UploadFile';
import { WireLinkSchema, WireUserSchema, type JsonApiResourceBody } from '../wireFragments';
import { AssistantAvatarResourceSchema } from './assistant-avatars.schema';

/**
 * The `assistants` JSON:API resource: the **read** (wire → domain) and
 * **write** (domain → wire) mapping, in one place — see
 * `api/schemas/resources/` for why every registered resource gets its own
 * file like this one, and `types/assistant/Assistant.ts` for the domain
 * shape both directions agree on.
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
 * | `assistant_setting_values: [{value, setting:{key}}]` | `formality` / `answerStyle` / `language` |
 * | `attachments: [{uuid, name, mime}]`    | `files: UploadFile[]`           |
 * | `_links: {edit: {meta:{message}}}`     | `actionPermissions: {edit: bool}` |
 * | `assistant_avatar: {icon_css}`         | `avatar: {iconCss}`             |
 *
 * Expressing the read half as a Zod `.transform()` means one declaration both
 * validates the response and produces the domain object.
 *
 * Writing (`assistantToApi`) stays hand-written rather than the mechanical
 * inverse of the transform, on purpose:
 *  - It emits a *partial* body driven by `changedKeys`, so the builder can
 *    PATCH only what the user touched. A schema describes one fixed shape and
 *    cannot express that.
 *  - Relationships are written as JSON:API resource identifiers
 *    (`{type, id}`), which is a different shape from the inlined objects that
 *    come back on read.
 *
 * ## Includes
 *
 * Every relationship is optional: it is only present when the request asked for
 * it via `include`. A missing relationship means "not loaded", so the transform
 * falls back to the same defaults `createEmptyAssistant()` uses rather than
 * inventing data.
 */

const WireCategorySchema = AssistantCategorySchema;
const WireTagSchema = AssistantTagSchema;
/** `assistant_avatar`, inlined on an assistant response — same shape as the standalone `assistant-avatars` resource. */
const WireAvatarSchema = AssistantAvatarResourceSchema;

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
    answer_style: 'answerStyle'
} as const satisfies Record<string, 'formality' | 'language' | 'answerStyle'>;

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
const AssistantsSchema: z.ZodType<Assistant> = AssistantResourceSchema.transform((wire): Assistant => ({
    id: wire.id,

    name: wire.name ?? '',
    handle: wire.handle ?? null,
    systemPrompt: wire.system_prompt ?? '',
    greeting: wire.greeting ?? '',
    description: wire.description ?? '',
    detailDescription: wire.detail_description ?? '',
    allowRemix: wire.allow_remix ?? false,
    // allowModelSelect: wire.allow_model_select ?? false,
    allowModelSelect: false,
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
    answerStyle: settingValue(wire.assistant_setting_values, 'answer_style'),
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
        changedKeys: version.changed_keys ?? null,
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
}));

export default AssistantsSchema;

// ─────────────────────────────────────────────────────────────────────────
// Write: object → JSON:API request body, plus the blank assistant the
// builder starts from. Kept in this same file — not a separate serializer —
// so read and write for one resource always live in one place.
//
// Writing stays hand-written rather than a schema: `assistantToApi` emits a
// *partial* body driven by `changedKeys`, and relationships are written as
// resource identifiers (`{type, id}`), a different shape from the inlined
// objects the read side above produces.
// ─────────────────────────────────────────────────────────────────────────

/**
 * A blank assistant: the builder's initial state before the server has minted
 * a record.
 *
 * Empty strings rather than `null` for the text fields, because that is what
 * `AssistantSchema` declares — the return type is checked against it, no cast.
 * (The old version cast a null-filled object with `as Assistant`, which also
 * quietly broke the builder's `description !== ''` completeness rule: `null`
 * is not `''`, so a blank description reported as filled.)
 */
export function createEmptyAssistant(): Assistant {
    return {
        id: null,
        name: '',
        handle: null,
        systemPrompt: '',
        greeting: '',
        starterPrompts: [],
        description: '',
        detailDescription: '',

        model: '',
        allowModelSelect: false,
        maxTokens: 2048,
        temp: 0,
        topP: 0,
        allowRemix: false,
        releaseStage: ReleaseMode.DRAFT,
        requested_release_stage: null,
        riskLevel: null,
        riskNote: null,
        usageCount: null,
        isFavorite: false,
        createdAt: '',
        updatedAt: '',

        formality: null,
        answerStyle: null,
        language: null,

        category: null,
        avatar: {
            name: '📚',
            iconCss: BACKGROUNDS[0].value
        },
        tags: [],
        creator: { id: '', displayName: '' },
        versions: [],
        files: [],
        knowledgeBases: [],
        submissionNote: '',

        actionPermissions: null,

        remixCreator: null,
        remixedAssistant: null,
    };
}

/**
 * Domain-to-wire mapping for {@link assistantToApi}. Deliberately excludes
 * every attribute the backend marks `readOnly()` on `AssistantSchema::fields()`
 * — `release_stage`, `requested_release_stage`, `created_at`, `updated_at`,
 * `is_favorite` — even in "send everything" mode (`changedKeys` omitted):
 * submitting a read-only attribute is a validation error regardless of
 * whether the value actually changed, so there's never a correct time to
 * write these. `release_stage` has its own write path — the `actions/release`
 * endpoint via {@link BuilderContext.requestRelease} — not a plain attribute
 * PATCH.
 */
export function assistantToApi(
    assistant: Assistant,
    changedKeys?: Set<AssistantKey>,
): JsonApiResourceBody {
    const include = (key: AssistantKey) =>
        !changedKeys || changedKeys.has(key);
    return {
        type: 'assistants',
        id: assistant.id,
        attributes: {
            ...(include('name') && { name: assistant.name }),
            ...(include('handle') && { handle: assistant.handle }),
            ...(include('systemPrompt') && { system_prompt: assistant.systemPrompt }),
            ...(include('greeting') && { greeting: assistant.greeting }),
            ...(include('description') && { description: assistant.description }),
            ...(include('detailDescription') && {
                detail_description: assistant.detailDescription,
            }),
            ...(include('allowRemix') && { allow_remix: assistant.allowRemix }),
            ...(include('allowModelSelect') && {
                // allow_model_select: assistant.allowModelSelect,
                allow_model_select: false,
            }),
            ...(include('model') && { model: assistant.model }),
            ...(include('maxTokens') && { max_tokens: assistant.maxTokens }),
            ...(include('temp') && { temp: assistant.temp }),
            ...(include('topP') && { top_p: assistant.topP }),
        },
        relationships: {
            ...(include('category') &&
                assistant.category && {
                    assistant_category: {
                        data: {
                            type: 'assistant-categories',
                            id: assistant.category.id,
                        },
                    },
                }),

            ...(include('tags') &&
                assistant.tags && {
                    assistant_tags: {
                        data: assistant.tags.map((t) => ({
                            type: 'assistant-tags',
                            id: t.id,
                        })),
                    },
                }),

            ...(include('aiTools') &&
                assistant.aiTools && {
                    ai_tools: {
                        data: assistant.aiTools.map((tool) => ({
                            type: 'ai-tools',
                            id: String(tool.id),
                        })),
                    },
                }),
        },
    };
}

// ─────────────────────────────────────────────────────────────────────────
// Assistant-setting field mapping — shared by the write side above (once it
// grows setting-value writes) and by the builder's error routing.
// ─────────────────────────────────────────────────────────────────────────

/** Maps an {@link Assistant} option field to its `key` in the options store. */
export const ASSISTANT_SETTING_KEY_MAP: Partial<
    Pick<Assistant, 'formality' | 'language' | 'answerStyle'>
> = {
    formality: 'formality',
    language: 'language',
    answerStyle: 'answer_style',
};

/** The `Assistant` fields that are persisted as setting values (see `assistant-settings.schema.ts`). */
export const ASSISTANT_SETTING_KEYS = Object.keys(
    ASSISTANT_SETTING_KEY_MAP,
) as (keyof Assistant)[];

/**
 * Inverse of the write mapping in {@link assistantToApi}: maps a JSON:API field
 * name (the snake_case attribute/relationship name the server reports in an
 * error pointer) back to its `Assistant` key. Used to route server validation
 * errors to the right field. Keep in sync with `assistantToApi`.
 *
 * Only covers the main assistant PATCH (`updateAssistant`/`assistantToApi`) —
 * its attribute/relationship names are the only ones that line up with this
 * table. The sub-resource save calls (`updateAssistantSetting`,
 * `createAssistantPrompts`/`removeAssistantPrompts`,
 * `createOrUpdateAssistantAvatar`) hit *different* JSON:API resources whose
 * own attribute names (`value`, `text`, `name`/`icon_css`) don't correspond
 * to an `Assistant` field at all — some even collide with one (an avatar's
 * `name` is not the assistant's `name`) — so those are routed directly via
 * `BuilderValidatorContext.recordFieldError()` in `updateServer()` instead of
 * through this table. Don't add sub-resource field names here.
 */
const API_FIELD_TO_KEY: Record<string, keyof Assistant> = {
    name: 'name',
    handle: 'handle',
    system_prompt: 'systemPrompt',
    greeting: 'greeting',
    description: 'description',
    detail_description: 'detailDescription',
    allow_remix: 'allowRemix',
    allow_model_select: 'allowModelSelect',
    release_stage: 'releaseStage',
    model: 'model',
    max_tokens: 'maxTokens',
    temp: 'temp',
    top_p: 'topP',
    is_favorite: 'isFavorite',
    // relationships
    assistant_category: 'category',
    assistant_tags: 'tags',
    ai_tools: 'aiTools',
};

/** Map a JSON:API error field name to its `Assistant` key, if known. */
export function apiFieldToAssistantKey(
    field?: string,
): keyof Assistant | undefined {
    return field ? API_FIELD_TO_KEY[field] : undefined;
}
