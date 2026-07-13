import z from 'zod';
import type { Assistant } from './Assistant';

/**
 * The assistant attributes that are driven by an {@link AssistantSettingSchema}
 * rather than by a plain input. They are saved through their own endpoint, so
 * the builder has to split them out of a normal field update.
 *
 * `satisfies` ties the list to the real assistant shape: renaming one of these
 * fields on {@link Assistant} breaks the build here instead of silently
 * dropping the setting.
 */
export const assistantSettingKeys = [
    'formality',
    'answerStyle',
    'language'
] as const satisfies readonly (keyof Assistant)[];

export type AssistantSettingKey = typeof assistantSettingKeys[number];

/** One selectable value of an {@link AssistantSettingSchema}; falls back to `value` when no `label` is given. */
export const AssistantSettingOptionSchema = z.object({
    label: z.string().optional(),
    value: z.string()
});

export type AssistantSettingOption = z.infer<typeof AssistantSettingOptionSchema>;

/**
 * A server-defined, tunable behavior knob offered by the builder (formality,
 * answer length, language, ...). The catalog is loaded once into
 * `assistantOptionsStore.settings`; the *chosen* value lives on the assistant
 * itself under the matching `key` (see `Assistant.formality` etc.).
 *
 * This is the domain shape. The wire shape differs (`ui_options` /
 * `default_value`, not `options` / `defaultValue`) and does no longer belong
 * here — see `api/schemas/resources/assistant-settings.schema.ts` for the
 * validated wire→domain mapping that produces this type; that file is what
 * `AssistantsPlugin.resourceSchemas()` registers.
 *
 * `options` is absent for free-text settings.
 */
export const AssistantSettingSchema = z.object({
    id: z.string(),
    /** Matches the assistant attribute this setting drives, e.g. `"formality"`. */
    key: z.string(),
    label: z.string(),
    description: z.string(),
    options: z.array(AssistantSettingOptionSchema).optional(),
    defaultValue: z.string().nullable().optional()
});

export type AssistantSetting = z.infer<typeof AssistantSettingSchema>;
