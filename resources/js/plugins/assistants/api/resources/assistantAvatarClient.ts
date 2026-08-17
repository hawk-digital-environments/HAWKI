import type {Assistant} from "$lib/plugins/assistants/types/assistant/Assistant";
import type {AssistantAvatar} from "$lib/plugins/assistants/types/assistant/AssistantAvatar";
import {logApiError} from "$lib/plugins/assistants/api/errors";
import {getAssistant} from "$lib/plugins/assistants/api/resources/assistantsClient";
import {avatarFromApi} from "$lib/plugins/assistants/api/serializers/avatarSerializer";



export async function getAssistantAvatar(id: string): Promise<AssistantAvatar|null> {
    // `getAssistant` already normalizes + logs; just map the avatar out here.
    const response = await getAssistant(id, {include: 'assistant_avatar'});
    return response.avatar ? avatarFromApi(response.avatar) : null;
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

async function createAssistantAvatar(body: JsonApiResourceBody): Promise<AssistantAvatar> {
    try {
        const response = await getApi().axios.post(`assistant-avatars`, { data: body });
        return avatarFromApi(response.data.data);
    } catch (err) {
        throw logApiError("createAssistantAvatar", err);
    }
}

async function updateAssistantAvatar(avatarId: string, body: JsonApiResourceBody): Promise<AssistantAvatar> {
    try {
        const response = await getApi().axios.patch(`assistant-avatars/${avatarId}`, { data: body });
        return avatarFromApi(response.data.data);
    } catch (err) {
        throw logApiError("updateAssistantAvatar", err, { avatarId });
    }
}
