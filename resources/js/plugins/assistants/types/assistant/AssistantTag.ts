import z from 'zod';

export const AssistantTagSchema = z.object({
    id: z.string(),
    text: z.string()
});

export type AssistantTag = z.infer<typeof AssistantTagSchema>;
