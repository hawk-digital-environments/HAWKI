import type {Assistant, AssistantAvatar} from "$plugins/assistants/types/assistant";
import {BACKGROUNDS} from "$plugins/assistants/presets/backgrounds";
import type {JsonApiResourceBody} from "./assistantSerializer";

export function avatarToApi(
    assistant: Assistant,
    avatar: AssistantAvatar
): JsonApiResourceBody {

    return {
        type: "assistant-avatars",
        ...(avatar.id ? { id: avatar.id } : {}),
        attributes: {
            name: avatar.name,
            icon_css: BACKGROUNDS.find((g) => g.value === avatar.iconCss)?.value,
        },
        relationships: {
            assistant: {
                data: {
                    type: "assistants",
                    id: assistant.id,
                }
            }
        }
    };
}


export function avatarFromApi(
    data: any
): AssistantAvatar {
    if(!data) throw new Error('No Assistant Avatar Data');
    return{
        id: data.id,
        iconCss: data.iconCss,
        name:data.name,
    }
}