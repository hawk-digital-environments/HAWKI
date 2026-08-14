import {AssistantFeedback} from "$plugins/assistants/types/assistant";
import {useApp} from "$lib/app/hooks/useApp.svelte";

export async function getAssistantFeedbacks(id: string): Promise<AssistantFeedback[]>
{
    const collection = await useApp().restApi.getResourceCollection(
        'assistant-feedback',
        {
            query: {
                include: ["user"]
            }
        }
    );
    console.log(collection)
    return Array.from(collection);
}

export async function submitAssistantFeedbacks(feedback: string, assistant: Assistant): Promise<AssistantFeedback>{
    try{
        const response = await getApi().postResource('assistant-feedback', {
                type: 'assistant-feedback',
                attributes: {
                    text: feedback
                },
                relationships:{
                    assistant: {
                        data: {
                            type: "assistants",
                            id: assistant.id
                        }
                    }
                },
            },
            {
                include: "user"
            }
        );
        return feedbackFromApi(response);
    }
    catch(err){
        throw logApiError("Send AssistantFeedback", err)

    }
}
