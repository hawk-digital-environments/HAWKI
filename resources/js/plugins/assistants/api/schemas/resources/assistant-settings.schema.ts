import z from 'zod';
import {
    AssistantSettingOptionSchema,
    type AssistantSetting
} from '$plugins/assistants/types/assistant/AssistantSetting';

/**
 * The `assistant-settings` JSON:API resource, as it arrives from the backend
 * (see `AssistantSettingSchema::fields()` in
 * `app/JsonApi/V1/AssistantSettings/AssistantSettingSchema.php`).
 *
 * The picker options and default live under `ui_options` / `default_value` on
 * the wire — not `options` / `defaultValue` as {@link AssistantSetting} names
 * them — and `RestApi` does no case conversion (see the doc comment on
 * `assistants.schema.ts`), so validating wire data straight against a schema
 * that only declares the domain names silently strips both fields instead of
 * failing loudly: they're optional, so Zod just leaves them `undefined`.
 */
const AssistantSettingResourceSchema = z.object({
    id: z.string(),
    /** Matches the assistant attribute this setting drives, e.g. `"formality"`. */
    key: z.string(),
    label: z.string(),
    description: z.string().nullable().optional(),
    /** Absent for free-text settings. */
    ui_options: z.array(AssistantSettingOptionSchema).nullable().optional(),
    default_value: z.string().nullable().optional()
});

/**
 * Registered by `AssistantsPlugin.resourceSchemas()` for
 * `RestApi.getResource(Collection)`. The catalog is loaded once into
 * `assistantOptionsStore.settings`; the *chosen* value lives on the assistant
 * itself under the matching `key` (see `Assistant.formality` etc., and
 * `assistants.schema.ts` for how `assistant_setting_values` maps onto it).
 */
const AssistantSettingsSchema = AssistantSettingResourceSchema.transform((wire): AssistantSetting => ({
    id: wire.id,
    key: wire.key,
    label: wire.label,
    description: wire.description ?? '',
    options: wire.ui_options ?? undefined,
    defaultValue: wire.default_value ?? null
}));

export default AssistantSettingsSchema;

// No `toApi`: a setting's *value* is written per-key through the dedicated
// `assistant-setting-values` endpoint (see `updateAssistantSetting` in
// `assistantsClient.ts`), not as a write to this catalog resource.
