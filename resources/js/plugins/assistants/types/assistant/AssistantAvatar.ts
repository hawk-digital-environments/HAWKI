import z from 'zod';

/**
 * The visual identity of an assistant: an emoji/icon rendered on top of a
 * background, both expressed as CSS the UI applies directly.
 *
 * `id` is absent until the avatar has been persisted — the builder POSTs it
 * separately from the rest of the assistant and writes the returned id back.
 */
export const AssistantAvatarSchema = z.object({
    id: z.string().optional(),
    /** Ready-to-apply CSS for the avatar (icon glyph plus background), see `presets/backgrounds.ts`. */
    iconCss: z.string(),
    name: z.string()
});

export type AssistantAvatar = z.infer<typeof AssistantAvatarSchema>;
