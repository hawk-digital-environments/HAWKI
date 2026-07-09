import z from 'zod';

export const WellKnownSystemPromptTypes = ['default', 'summary', 'title_generation', 'prompt_improvement'] as const;
export type WellKnownSystemPromptType = (typeof WellKnownSystemPromptTypes)[number];

const SystemPromptsSchema = z.object({
    id: z.string(),
    prompt_type: z.string(),
    usage_type: z.string(),
    locale: z.string(),
    prompt: z.string()
}).strict();

export default SystemPromptsSchema;

export type SystemPrompt = z.infer<typeof SystemPromptsSchema>;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'system-prompts': SystemPrompt;
    }
}
