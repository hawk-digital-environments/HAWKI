import z from "zod";
import { logApiError } from "$plugins/assistants/api/errors";
import { useApp } from "$lib/app/hooks/useApp.svelte";
import type { Assistant } from "$plugins/assistants/types/assistant";
import { BACKGROUNDS } from "$plugins/assistants/presets/backgrounds";

const ASSISTANTS = "assistants";

/**
 * The free-text draft fields the builder guide reads and may fill
 * (`AssistantBuilderGuideService::TEXT_FIELDS`). Handle, category, model,
 * the settings and the tags travel separately, see {@link BuilderGuideUpdates}.
 */
export const BUILDER_GUIDE_FIELDS = [
    "name",
    "description",
    "detailDescription",
    "systemPrompt",
    "greeting",
    "starterPrompts",
] as const satisfies readonly (keyof Assistant)[];

export type BuilderGuideField = (typeof BUILDER_GUIDE_FIELDS)[number];

/**
 * The settings fields the guide reads and may fill
 * (`AssistantBuilderGuideService::SETTING_FIELDS`); each value is one of the
 * setting's options.
 */
export const BUILDER_GUIDE_SETTING_FIELDS = [
    "language",
    "formality",
    "answerStyle",
] as const satisfies readonly (keyof Assistant)[];

export type BuilderGuideSettingField = (typeof BUILDER_GUIDE_SETTING_FIELDS)[number];

export type BuilderGuideMessage = { role: "user" | "assistant"; content: string };

const BuilderGuideResponseSchema = z.object({
    data: z.object({
        reply: z.string(),
        updates: z.object({
            name: z.string().optional(),
            handle: z.string().optional(),
            categoryId: z.string().optional(),
            model: z.string().optional(),
            description: z.string().optional(),
            detailDescription: z.string().optional(),
            systemPrompt: z.string().optional(),
            greeting: z.string().optional(),
            starterPrompts: z.array(z.string()).optional(),
            language: z.string().optional(),
            formality: z.string().optional(),
            answerStyle: z.string().optional(),
            /** Tag names: existing tags spelled as stored, new ones still to be created. */
            tags: z.array(z.string()).optional(),
            /** Either part may be missing when the guide's pick wasn't usable. */
            avatar: z.object({
                emoji: z.string().optional(),
                /** Id of one of the {@link BACKGROUNDS}. */
                background: z.string().optional(),
            }).optional(),
        }),
    }),
});

export type BuilderGuideUpdates = z.infer<typeof BuilderGuideResponseSchema>["data"]["updates"];

/**
 * One turn of the builder's guide chat (`POST assistants/{id}/actions/builder-guide`).
 * The server runs an LLM with structured output and returns its reply plus
 * the builder fields it wants filled; nothing is persisted server-side — the
 * caller applies `updates` to the draft, which then saves via autosave.
 */
export async function requestBuilderGuide(
    draft: Assistant,
    messages: BuilderGuideMessage[],
    signal?: AbortSignal,
): Promise<{ reply: string; updates: BuilderGuideUpdates }> {
    const draftFields = {
        ...Object.fromEntries(BUILDER_GUIDE_FIELDS.map((key) => [key, draft[key]])),
        ...Object.fromEntries(BUILDER_GUIDE_SETTING_FIELDS.map((key) => [key, draft[key] || null])),
        handle: draft.handle,
        categoryId: draft.category?.id ?? null,
        model: draft.model || null,
        tags: draft.tags.map((tag) => tag.text),
        avatar: {
            emoji: draft.avatar.name,
            background: BACKGROUNDS.find((bg) => bg.value === draft.avatar.iconCss)?.id ?? null,
        },
    };
    try {
        const response = await useApp().restApi.postToResourceAction(
            ASSISTANTS,
            `${draft.id}/actions/builder-guide`,
            { messages, draft: draftFields, avatarBackgrounds: BACKGROUNDS.map((bg) => bg.id) },
            { schema: BuilderGuideResponseSchema, signal },
        );
        return response.data;
    } catch (err) {
        throw logApiError("requestBuilderGuide", err, { assistantId: draft.id });
    }
}

/**
 * The guide's avatar pick alone: a one-off guide turn outside the chat, asked
 * for nothing but a fitting emoji and background. Anything else it proposes
 * is dropped.
 */
export async function requestAvatarSuggestion(
    draft: Assistant,
    signal?: AbortSignal,
): Promise<BuilderGuideUpdates["avatar"]> {
    const { updates } = await requestBuilderGuide(draft, [{
        role: "user",
        content: "Suggest a fresh avatar for this assistant: one fitting emoji and background, different from the current one. Change no other field.",
    }], signal);
    return updates.avatar;
}
