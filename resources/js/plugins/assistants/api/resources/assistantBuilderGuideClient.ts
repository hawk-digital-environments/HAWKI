import z from "zod";
import { logApiError } from "$plugins/assistants/api/errors";
import { useApp } from "$lib/app/hooks/useApp.svelte";
import type { Assistant } from "$plugins/assistants/types/assistant";

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
    };
    try {
        const response = await useApp().restApi.postToResourceAction(
            ASSISTANTS,
            `${draft.id}/actions/builder-guide`,
            { messages, draft: draftFields },
            { schema: BuilderGuideResponseSchema, signal },
        );
        return response.data;
    } catch (err) {
        throw logApiError("requestBuilderGuide", err, { assistantId: draft.id });
    }
}
