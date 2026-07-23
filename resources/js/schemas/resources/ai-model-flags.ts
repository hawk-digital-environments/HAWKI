import z from 'zod';

export const wellKnownAiModelFlags = [
    'open-weights',
    'eco-friendly',
    'self-hosted',
    'multi-modal',
    'strength-creative-writing',
    'strength-code-generation',
    'strength-math',
    'strength-role-playing',
    'strength-reasoning',
    'feature-streaming',
    'feature-sampling-parameters',
    'feature-response-schema',
    'feature-prompt-caching',
    'feature-reasoning-none',
    'feature-reasoning-minimal',
    'feature-reasoning-low',
    'feature-reasoning-medium',
    'feature-reasoning-high',
    'feature-reasoning-xhigh',
    'feature-reasoning-max'
] as const;
export type WellKnownAiModelFlag = typeof wellKnownAiModelFlags[number];

export const AiModelFlagsSchema = z.object({
    id: z.union([z.string(), z.enum(wellKnownAiModelFlags)]),
    title_label: z.string(),
    description_label: z.string(),
    /** {@see app/Services/Ai/Models/Flags/AiModelFlagRegistry.php} for the list of available color codes */
    color_code: z.union([z.string(), z.enum(['@default', '@success', '@warning', '@error', '@highlight'])]).nullable()
});

export default AiModelFlagsSchema;

export type AiModelFlag = z.infer<typeof AiModelFlagsSchema>;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'ai-model-flags': AiModelFlag;
    }
}
