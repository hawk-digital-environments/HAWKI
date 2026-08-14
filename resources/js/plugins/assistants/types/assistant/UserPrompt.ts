import z from 'zod';

/**
 * A single starter prompt suggested to the user when they open an assistant.
 *
 * The wire/relationship shape is this object; the builder flattens it to plain
 * strings in `Assistant.starterPrompts`, so a serializer maps between the two.
 */
export const UserPromptSchema = z.object({
    text: z.string()
});

export type UserPrompt = z.infer<typeof UserPromptSchema>;
