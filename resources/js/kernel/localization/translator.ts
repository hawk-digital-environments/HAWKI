import type {TranslationLabels} from '$lib/app/schemas/resources/translation-labels.schema.js';
import {strtr, ucfirst} from '$lib/utils/strings.js';

export type ReplacementValue = Record<string, string | ((content: string) => string)>;

export interface Translator {
    /**
     * Returns `true` when a translation entry exists for the given key.
     *
     * @param label - Translation key (supports dot notation).
     */
    hasLabel(label: string): boolean;

    /**
     * Translates a label key into the corresponding localised string.
     *
     * Supports dot-notation for nested translation keys (e.g. `'section.subsection.key'`).
     *
     * Placeholder replacement is compatible with Laravel's `Translator::makeReplacements()`:
     * - **Function values** – when a replacement value is a `Function`, every occurrence of
     *   `<key>content</key>` in the string is replaced by the return value of `fn(content)`.
     * - **String values** – three variants are substituted simultaneously (longest-match-first,
     *   mirroring PHP's `strtr`):
     *   - `:KEY`  → value converted to upper-case
     *   - `:Key`  → value with first letter upper-cased
     *   - `:key`  → value as-is
     *
     * @param label - Translation key, optionally using dot notation.
     * @param replacements - Map of placeholder keys to
     *   replacement values or callback functions.
     * @param ignoreMissing - When `true`, missing translation keys will return an empty string instead of a "Missing translation" message. (Default: `false`)
     * @returns The translated, interpolated string, or a "Missing translation: …" message
     *   when the key cannot be found.
     */
    translate(label: string, replacements?: ReplacementValue, ignoreMissing?: boolean): string;

    /**
     * An alias for `translate()`, to allow using the `__()` function in templates (similar to Laravel).
     */
    __(label: string, replacements?: ReplacementValue, ignoreMissing?: boolean): string;

    /**
     * Translates a label key into the corresponding localised string or object.
     *
     * This is similar to `translate()`, but allows returning non-string values (e.g. for nested sections of labels).
     * Placeholder replacement only works when the resolved label is a string; otherwise, replacements are ignored.
     *
     * @param label - Translation key, optionally using dot notation.
     * @param replacements - Map of placeholder keys to
     *   replacement values or callback functions.
     * @param ignoreMissing - When `true`, missing translation keys will return an empty string instead of a "Missing translation" message. (Default: `false`)
     * @returns The translated, interpolated string or object, or a "Missing translation: …" message
     *   when the key cannot be found.
     */
    getTranslations(label: string, replacements?: ReplacementValue, ignoreMissing?: boolean): Record<string, any> | string | null;

    /**
     * Works similar to {@link getTranslations}, but instead of returning a nested object of translation labels,
     * it flattens the result into a single-level object with dot-notated keys.
     * Additionally, it ALWAYS expects your path to point to a nested object, and will throw an error if it points to a string or a non-object value.
     *
     * @param path
     */
    getTranslationsFlat(path: string): Record<string, string>;
}

export function createTranslator(
    labelGetter: () => TranslationLabels['labels']
) {
    // We don't use a class here, because it would not survive potential deconstruction when using the hook with `const { translate } = useTranslator()`.

    function hasLabel(label: string): boolean {
        return findRecursively(labelGetter(), label) !== null;
    }

    function translate(label: string, replacements?: ReplacementValue, ignoreMissing: boolean = false): string {
        const result = getTranslations(label, replacements, ignoreMissing);
        if (typeof result === 'string') {
            return result;
        }
        console.warn(`Translation for label "${label}" is not a string:`, result);
        return `${label}`;
    }

    function getTranslations(label: string, replacements?: ReplacementValue, ignoreMissing: boolean = false): Record<string, any> | string | null {
        if (!label) {
            console.warn('Empty translation label provided!');
            return '[[Empty translation label]]';
        }

        const resolvedLabel: Record<string, any> | string | null = findRecursively(labelGetter(), label);
        if (resolvedLabel === null) {
            if (ignoreMissing) {
                return '';
            }
            console.warn(`Translation for label "${label}" not found.`);
            return `Missing translation: ${label}`;
        }

        if (!replacements || Object.keys(replacements).length === 0 || typeof resolvedLabel !== 'string') {
            return resolvedLabel;
        }

        let finalLabel = resolvedLabel;
        const shouldReplace: Record<string, string> = {};

        for (const [key, value] of Object.entries(replacements)) {
            if (typeof value === 'function') {
                // Replace <key>inner</key> occurrences via the callback
                finalLabel = finalLabel.replace(
                    new RegExp(`<${key}>(.*?)<\\/${key}>`, 'g'),
                    (_, inner) => value(inner)
                );
                continue;
            }

            const strValue = (value ?? '') + '';
            // Longest keys must win → add all three variants; strtr() handles priority
            shouldReplace[`:${key.toUpperCase()}`] = strValue.toUpperCase();
            shouldReplace[`:${ucfirst(key)}`] = ucfirst(strValue);
            shouldReplace[`:${key}`] = strValue;
        }

        return strtr(finalLabel, shouldReplace);
    }

    function getTranslationsFlat(path: string): Record<string, string> {
        const labels = labelGetter();
        if (!labels) {
            console.warn('Translation labels not loaded yet, returning empty object as fallback:', path);
            return {};
        }

        const resolvedLabel: Record<string, any> | string | null = findRecursively(labels, path);

        if (resolvedLabel === null) {
            console.warn(`Translation for path "${path}" not found.`);
            return {};
        }

        if (typeof resolvedLabel !== 'object' || Array.isArray(resolvedLabel)) {
            throw new Error(`Expected a nested object at path "${path}", but found a non-object value.`);
        }

        const flatResult: Record<string, string> = {};

        function flatten(obj: Record<string, any>, prefix: string = '') {
            for (const [key, value] of Object.entries(obj)) {
                const newKey = prefix ? `${prefix}.${key}` : key;
                if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                    flatten(value, newKey);
                } else if (typeof value === 'string') {
                    flatResult[newKey] = value;
                } else {
                    console.warn(`Skipping non-string value at path "${newKey}".`);
                }
            }
        }

        flatten(resolvedLabel);

        return flatResult;
    }

    return {
        hasLabel,
        translate,
        __: translate,
        getTranslations,
        getTranslationsFlat
    };
}

/**
 * Resolves a (possibly dot-notated) key inside a nested object.
 *
 * @param obj The object to search in.
 * @param key Simple or dot-notated key (e.g. `'a.b.c'`).
 */
function findRecursively(obj: Record<string, any>, key: string) {
    if (key.indexOf('.') > -1) {
        const parts = key.split('.');
        let current = obj;
        for (const part of parts) {
            if (current[part] === undefined) {
                return null;
            }
            current = current[part];
        }
        return current;
    } else {
        return obj[key] !== undefined ? obj[key] : null;
    }
}
