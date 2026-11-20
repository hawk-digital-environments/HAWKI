import type {AssistantAvatar} from "$plugins/assistants/types/assistant/AssistantAvatar";
import {logApiError} from "$plugins/assistants/api/errors";
import {getAssistant} from "$plugins/assistants/api/resources/assistantsClient";
import {useApp} from "$lib/app/hooks/useApp.svelte.js";
import type {JsonApiResourceBody} from "$plugins/assistants/api/schemas/wireFragments";

const ASSISTANT_AVATARS = "assistant-avatars";



export async function getAssistantAvatar(id: string): Promise<AssistantAvatar|null> {
    // `getAssistant` already validates + maps the whole assistant (including
    // its inlined avatar) via `assistants.schema.ts` — `response.avatar` is
    // already a domain `AssistantAvatar`, not wire data, so no further
    // parsing belongs here.
    const response = await getAssistant(id, {include: 'assistant_avatar'});
    return response.avatar ?? null;
}


export async function createOrUpdateAssistantAvatar(
    assistantId: string,
    body: JsonApiResourceBody
): Promise<AssistantAvatar> {
    if (!body.id) {
        const existing = await getAssistantAvatar(assistantId);
        if (existing?.id) {
            body.id = existing.id;
        } else {
            return await createAssistantAvatar(body);
        }
    }

    try {
        return await updateAssistantAvatar(body.id, body);
    } catch (err) {
        // Stale/invalid id - re-check actual state and recover once
        const existing = await getAssistantAvatar(assistantId);

        if (!existing) {
            return await createAssistantAvatar(body);
        }
        if (existing.id && existing.id !== body.id) {
            return await updateAssistantAvatar(existing.id, body);
        }
        throw err;
    }
}

/**
 * `restApi.createResource`/`updateResource` already decode + validate the
 * response through the registered `assistant-avatars` schema (see
 * `AssistantAvatarsSchema` and `RestApi.writeResource`), so the result here
 * is already a mapped `AssistantAvatar` — no manual parsing needed.
 */
async function createAssistantAvatar(body: JsonApiResourceBody): Promise<AssistantAvatar> {
    try {
        return await useApp().restApi.createResource(ASSISTANT_AVATARS, body.attributes ?? {}, {
            relationships: body.relationships,
        });
    } catch (err) {
        throw logApiError("createAssistantAvatar", err);
    }
}

async function updateAssistantAvatar(avatarId: string, body: JsonApiResourceBody): Promise<AssistantAvatar> {
    try {
        return await useApp().restApi.updateResource(ASSISTANT_AVATARS, avatarId, body.attributes ?? {}, {
            relationships: body.relationships,
        });
    } catch (err) {
        throw logApiError("updateAssistantAvatar", err, { avatarId });
    }
}
