import z from 'zod';
import AiToolsSchema from '$plugins/core/schemas/resources/ai-tools.schema.js';
import { UploadFileSchema } from '../UploadFile';
import { AssistantAvatarSchema } from './AssistantAvatar';
import { AssistantFeedbackSchema } from './AssistantFeedback';
import { AssistantCategorySchema } from './AssistantCategory';
import { CreatorSchema } from './Creator';
import { ReleaseMode } from './ReleaseMode';
import { RiskLevel } from './RiskLevel';
import { AssistantTagSchema } from './AssistantTag';
import { VersionSchema } from './Version';
import {AiModelDescription} from "$plugins/core/schemas/resources/ai-model-descriptions";

/**
 * The single working shape of an assistant across the whole app — list, card,
 * detail, and the builder all consume this exact object.
 *
 * It mirrors the JSON:API resource *after* the client has deserialized it
 * (`keyForAttribute: "camelCase"`): attributes are flat camelCase, and the
 * relationships the server inlines (`category`, `tags`, `creator`, `versions`)
 * arrive as nested value objects. Read paths (`getAssistant`, `listAssistants`)
 * hand these straight through; the builder builds/serializes them via
 * `assistantSerializer` (`assistantFromApi` / `assistantToApi`).
 *
 * `id` is a string because JSON:API resource ids are strings on the wire.
 *
 * Optional (`?`) fields are the ones only some endpoints include — they depend
 * on the request's `include`/permissions, so a missing key means "not loaded",
 * not "empty". Nullable fields are always present but may carry no value.
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'assistants': Assistant;
    }
}

export const AssistantSchema = z.object({
    id: z.string().nullable(),

    // ─── Attributes ──────────────────────────────────────────────────────────

    name: z.string(),
    /** URL-safe short name, minted server-side; `null` until the assistant is released. */
    handle: z.string().nullable(),

    systemPrompt: z.string(),
    greeting: z.string(),

    /** One-liner shown on the assistant card. */
    description: z.string(),
    /** Long-form text shown on the detail page. */
    detailDescription: z.string(),

    allowRemix: z.boolean(),
    allowModelSelect: z.boolean(),

    releaseStage: z.enum(ReleaseMode),
    /**
     * The stage the creator has asked for but that review has not granted yet;
     * `null` when there is no pending request.
     *
     * Snake_case on purpose: this one attribute is not camelized by the
     * deserializer, and the builder reads it under this exact key.
     */
    requested_release_stage: z.enum(ReleaseMode).nullable(),

    /** Trust & risk classification shown on the detail page. Optional: the
        backend does not yet serve this; it is currently populated from mocks. */
    riskLevel: z.enum(RiskLevel).nullable(),
    riskNote: z.string().nullable(),

    /** Cumulative usage/view count shown on the detail page. Optional: not yet
        served by the backend; currently populated from mocks. */
    usageCount: z.number().nullable(),

    /** Suggested opening prompts, flattened from the `user-prompts` relationship. */
    starterPrompts: z.array(z.string()),

    /** Provider-side model identifier the assistant runs on, e.g. `gpt-4o`. */
    model: z.string(),

    maxTokens: z.number(),
    temp: z.number(),
    topP: z.number(),

    // Assistant settings — each is `null` until the creator picks a value; the
    // selectable options come from `assistantOptionsStore.settings`.
    formality: z.string().nullable(),
    answerStyle: z.string().nullable(),
    language: z.string().nullable(),

    avatar: AssistantAvatarSchema,

    createdAt: z.string(),
    updatedAt: z.string(),

    /** Whether the *current* user has favorited this assistant. */
    isFavorite: z.boolean(),

    // ─── Relationships ───────────────────────────────────────────────────────

    category: AssistantCategorySchema.nullable(),

    tags: z.array(AssistantTagSchema),
    creator: CreatorSchema,
    versions: z.array(VersionSchema),

    /** Author of the assistant this one was remixed from; `null` for originals. */
    remixCreator: CreatorSchema.nullable(),
    /**
     * The assistant this one was remixed from; `null` for originals.
     *
     * Self-referential, so it is declared as a getter — Zod resolves the
     * reference lazily and TypeScript still infers the recursive type.
     */
    get remixedAssistant() {
        return AssistantSchema.nullable();
    },

    /** Knowledge files attached to the assistant. Only included on the owner-only edit fetch (`include=attachments`). */
    files: z.array(UploadFileSchema).optional(),
    knowledgeBases: z.array(z.string()).optional(),
    /** Message the creator sends along with a release request, for the reviewer. */
    submissionNote: z.string().optional(),

    aiTools: z.array(AiToolsSchema).optional(),

    /** Map of action name → whether the current user may perform it (`edit`, `remix`, ...); `null` when the server did not evaluate permissions. */
    actionPermissions: z.record(z.string(), z.boolean()).nullable(),
    feedbacks: z.array(AssistantFeedbackSchema).optional()
});

export type Assistant = z.infer<typeof AssistantSchema>;

/** Any field of an assistant — what the builder's diffing/`set()` APIs are keyed by. */
export type AssistantKey = keyof Assistant;



