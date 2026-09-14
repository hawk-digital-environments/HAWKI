import type { AiMessage } from '$lib/kernel/ai/types.js';

export interface TranslationTarget {
    /** Locale code, e.g. `de_DE`. Used as key in the result. */
    lang: string;
    /** Human readable language name, passed to the model. */
    name: string;
}

export interface TranslateLocalesOptions {
    text: string;
    source: TranslationTarget;
    targets: TranslationTarget[];
    /** Sends one request and resolves with the model's plain text answer (see `AiApi.text`). */
    complete: (messages: AiMessage[]) => Promise<string>;
}

export function translationMessages(text: string, source: TranslationTarget, target: TranslationTarget): AiMessage[] {
    const system =
        `You are a professional translator. Translate the user's text from ${source.name} (${source.lang}) into ${target.name} (${target.lang}).\n` +
        'The text is Markdown. Keep all Markdown formatting, headings, lists, links, URLs, code blocks, inline code and placeholders exactly as they are.\n' +
        'Return only the translated text without any explanation, quotes or code fences around it.';
    return [
        { role: 'system', content: { text: system } },
        { role: 'user', content: { text } }
    ];
}

/**
 * Translates one locale's text into every other locale with a single AI request per
 * target. All requests run in parallel; a single failure rejects the whole call so the
 * caller never applies a half-translated set.
 */
export async function translateLocales({ text, source, targets, complete }: TranslateLocalesOptions): Promise<Record<string, string>> {
    const others = targets.filter((target) => target.lang !== source.lang);
    const translations = await Promise.all(
        others.map(async (target) => [target.lang, (await complete(translationMessages(text, source, target))).trim()] as const)
    );
    return Object.fromEntries(translations.filter(([, translated]) => translated !== ''));
}
