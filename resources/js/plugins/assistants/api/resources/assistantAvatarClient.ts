import type {AssistantAvatar} from "$plugins/assistants/types/assistant/AssistantAvatar";
import {logApiError} from "$plugins/assistants/api/errors";
import {getAssistant} from "$plugins/assistants/api/resources/assistantsClient";
import AssistantAvatarsSchema from "$plugins/assistants/api/schemas/resources/assistant-avatars.schema";
import type {JsonApiResourceBody} from "$plugins/assistants/api/schemas/wireFragments";



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

// @todo `getApi()` is not a defined helper anywhere in this codebase — these
// two go through `useApp().restApi` (like every other client in this plugin)
// once the create/update avatar endpoints are actually wired up. Left as-is
// rather than guessed at: not part of this cleanup's scope (see the "post
// functions aren't implemented yet" note this schema reorg was asked to keep
// in mind). `AssistantAvatarsSchema.parse(...)` below assumes a jsona-decoded
// response shape (flat `icon_css`, not the raw `{data: {attributes: {...}}}`
// envelope `axios.post` would actually return) — whoever wires this up for
// real should go through `restApi` instead, which decodes for you.
async function createAssistantAvatar(body: JsonApiResourceBody): Promise<AssistantAvatar> {
    try {
        const response = await getApi().axios.post(`assistant-avatars`, { data: body });
        return AssistantAvatarsSchema.parse(response.data.data);
    } catch (err) {
        throw logApiError("createAssistantAvatar", err);
    }
}

async function updateAssistantAvatar(avatarId: string, body: JsonApiResourceBody): Promise<AssistantAvatar> {
    try {
        const response = await getApi().axios.patch(`assistant-avatars/${avatarId}`, { data: body });
        return AssistantAvatarsSchema.parse(response.data.data);
    } catch (err) {
        throw logApiError("updateAssistantAvatar", err, { avatarId });
    }
}
