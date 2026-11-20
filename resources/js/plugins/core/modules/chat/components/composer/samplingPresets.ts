/**
 * Temperature / Top P presets offered wherever sampling parameters are
 * configured (the composer's settings menu and the assistant builder).
 * `labelKey` is a translator key.
 */
export const samplingPresets = [
    {key: 'creative', labelKey: 'chat.composer.settings.presetCreative', temp: 1.4, topP: 0.95},
    {key: 'balanced', labelKey: 'chat.composer.settings.presetBalanced', temp: 0.7, topP: 0.9},
    {key: 'precise', labelKey: 'chat.composer.settings.presetPrecise', temp: 0.2, topP: 0.5}
] as const;

export type SamplingPresetKey = typeof samplingPresets[number]['key'];
