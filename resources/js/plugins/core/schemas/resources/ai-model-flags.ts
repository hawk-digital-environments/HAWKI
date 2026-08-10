import z from 'zod';

/**
 * Validates the `ai-model-flags` API resource — the catalog of descriptive badges (open-weights,
 * eco-friendly, strengths, streaming/reasoning-effort features, ...) that can be attached to an
 * {@link import('./ai-models.schema.js').AiModel} via its `flags` array. This module also exports
 * {@link wellKnownAiModelFlags}, the shared list of built-in flag ids, which `ai-models.schema.ts`
 * imports to type-check a model's `flags` field.
 *
 * Registers the resource under the key `'ai-model-flags'` in `HawkiResourceSchemas` (see the
 * `declare module` augmentation below).
 */
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
    /** Flag identifier. One of {@link wellKnownAiModelFlags} for built-in flags, or an arbitrary string for flags added by other plugins/backends. */
    id: z.union([z.string(), z.enum(wellKnownAiModelFlags)]),
    title_label: z.string(),
    description_label: z.string(),
    /** {@see app/Services/Ai/Models/Flags/AiModelFlagRegistry.php} for the list of available color codes */
    color_code: z.union([z.string(), z.enum(['@default', '@success', '@warning', '@error', '@highlight'])]).nullable()
});

export default AiModelFlagsSchema;

export type AiModelFlag = z.infer<typeof AiModelFlagsSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'ai-model-flags': AiModelFlag;
    }
}
