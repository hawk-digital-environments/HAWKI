import z from 'zod';

export const LocalesSchema = z.object({
    /**
     * The locale code, e.g. "en_US" or "de_DE".
     */
    lang: z.string(),
    /**
     * The HTML-compatible locale code, e.g. "en-US" or "de-DE".
     */
    htmlLang: z.string(),
    /**
     * The name of the language in the language itself, e.g. "English" or "Deutsch".
     */
    nameInLanguage: z.string(),
    /**
     * The short name of the language, e.g. "EN" or "DE".
     */
    shortName: z.string()
});

export type Locale = z.infer<typeof LocalesSchema>;
