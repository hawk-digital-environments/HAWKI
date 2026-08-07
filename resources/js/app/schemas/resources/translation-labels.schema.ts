import z from 'zod';

const TranslationLabelsSchema = z.object({
    locale: z.string(),
    labels: z.object().loose()
});

export default TranslationLabelsSchema;

export type TranslationLabels = z.infer<typeof TranslationLabelsSchema>;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'translation-labels': TranslationLabels;
    }
}
