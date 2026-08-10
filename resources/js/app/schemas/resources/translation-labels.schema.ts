import z from 'zod';

/**
 * Zod schema for the `translation-labels` JSON:API resource.
 *
 * The server serves one record per locale: `locale` identifies the language
 * (e.g. `'en_US'`) and `labels` is a flat `key → translated string` map the
 * {@link LocalizationExtension} loads and hands to the {@link Translator}.
 * `labels` is `.loose()` because each locale may carry a different subset of
 * keys (translations land incrementally) and the server returns the full set
 * it currently has — the client just looks up by key at render time.
 *
 * Augments {@link HawkiResourceSchemas} via declaration merging below so the
 * `RestApi` typed accessor (`getResource('translation-labels', lang)`) returns
 * a typed {@link TranslationLabels}.
 */
const TranslationLabelsSchema = z.object({
    /** The locale code this label set belongs to (matches a `Locale.lang`). */
    locale: z.string(),
    /** Flat `key → translated string` map; `.loose()` so unknown keys are kept. */
    labels: z.object().loose()
});

export default TranslationLabelsSchema;

export type TranslationLabels = z.infer<typeof TranslationLabelsSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'translation-labels': TranslationLabels;
    }
}
