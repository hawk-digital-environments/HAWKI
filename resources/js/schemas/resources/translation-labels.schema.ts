import z from 'zod';

const TranslationLabelsSchema = z.object({
    locale: z.string(),
    labels: z.object().loose()
});

export default TranslationLabelsSchema;

export type TranslationLabels = z.infer<typeof TranslationLabelsSchema>;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'translation-labels': TranslationLabels;
    }
}
