import type {Assistant, AssistantFeedback} from "$plugins/assistants/types/assistant";
import {useApp} from "$lib/app/hooks/useApp.svelte";
import {logApiError} from "$plugins/assistants/api/errors";
import AssistantFeedbackSchema, {feedbackToApi} from "$plugins/assistants/api/schemas/resources/assistant-feedback.schema";

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
    return Array.from(collection);
}

// @todo `getApi()` is not a defined helper anywhere in this codebase — this
// goes through `useApp().restApi.postResource(...)` (like every other write
// in this plugin) once actually wired up. Left as-is rather than guessed at.
export async function submitAssistantFeedbacks(feedback: string, assistant: Assistant): Promise<AssistantFeedback>{
    try{
        const response = await getApi().postResource(
            'assistant-feedback',
            feedbackToApi(feedback, assistant),
            {
                include: "user"
            }
        );
        return AssistantFeedbackSchema.parse(response);
    }
    catch(err){
        throw logApiError("Send AssistantFeedback", err)

    }
}
