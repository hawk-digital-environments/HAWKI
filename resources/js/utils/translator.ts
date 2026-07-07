import {getConfig} from '$lib/data/config/config.js';
import type {TranslationLabels} from '$lib/schemas/resources/translation-labels.schema.js';
import {getResourceFromApi} from '$lib/data/api/api.js';
import {getConnection} from '$lib/data/connection/connection.js';

type ReplacementValue = Record<string, string | ((content: string) => string)>;

let loadedLabels: TranslationLabels | null = null;

/**
 * Fetches and caches the translation labels for the current locale.
 *
 * Called once during the bootstrap sequence before any `__()` calls are made.
 * If the current locale fails to load, falls back to the configured default
 * locale. If that also fails, an empty label set is used so `__()` still
 * returns a visible "Missing translation:" string rather than throwing.
 */
export async function loadTranslationLabels(): Promise<void> {
    const currentLocale = getConnection().locale;
    try {
        loadedLabels = await getResourceFromApi('translation-labels', currentLocale);
    } catch (error) {
        console.error('Failed to load translation labels for locale', currentLocale, error);
        const defaultLocale = getConfig().locale.default;
        if (currentLocale !== defaultLocale) {
            console.warn(`Falling back to default locale "${defaultLocale}".`);
            try {
                loadedLabels = await getResourceFromApi('translation-labels', defaultLocale);
            } catch (fallbackError) {
                console.error('Failed to load translation labels for default locale as well', defaultLocale, fallbackError);
                loadedLabels = {locale: currentLocale, labels: {}};
            }
        }
    }
}

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
export function __(label: string, replacements?: ReplacementValue, ignoreMissing: boolean = false) {
    const result = getTranslations(label, replacements, ignoreMissing);
    if (typeof result === 'string') {
        return result;
    }
    console.warn(`Translation for label "${label}" is not a string:`, result);
    return `Invalid translation (not a string): ${label}`;
}

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
export function getTranslations(label: string, replacements?: ReplacementValue, ignoreMissing: boolean = false): Record<string, any> | string | null {
    if (!label) {
        console.warn('Empty translation label provided!');
        return '[[Empty translation label]]';
    }
    if (!loadedLabels) {
        console.warn('Translation labels not loaded yet, returning label key as fallback:', label);
        return label;
    }

    const resolvedLabel: Record<string, any> | string | null = findRecursively(loadedLabels.labels, label);
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

/**
 * Works similar to {@link getTranslations}, but instead of returning a nested object of translation labels,
 * it flattens the result into a single-level object with dot-notated keys.
 * Additionally, it ALWAYS expects your path to point to a nested object, and will throw an error if it points to a string or a non-object value.
 *
 * @param path
 */
export function getTranslationsFlat(path: string): Record<string, string> {
    if (!loadedLabels) {
        console.warn('Translation labels not loaded yet, returning empty object as fallback:', path);
        return {};
    }

    const resolvedLabel: Record<string, any> | string | null = findRecursively(loadedLabels.labels, path);

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

/**
 * Returns `true` when a translation entry exists for the given key.
 *
 * @param label - Translation key (supports dot notation).
 */
export function hasTranslation(label: string) {
    return findRecursively(loadedLabels?.labels ?? [], label) !== null;
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

/**
 * Capitalises the first character of a string (equivalent to PHP's `Str::ucfirst`).
 *
 * @param str
 */
function ucfirst(str: string) {
    if (!str) return str;
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Replaces multiple substrings in one pass, preferring longer keys over shorter ones –
 * matching PHP's `strtr($str, $pairs)` behaviour.
 *
 * @param str - The source string.
 * @param pairs - Map of search-strings to replacement-strings.
 */
function strtr(str: string, pairs: Record<string, string>) {
    // Sort keys longest-first so longer placeholders are matched preferentially
    const keys = Object.keys(pairs).sort((a, b) => b.length - a.length);
    let result = '';
    let i = 0;
    while (i < str.length) {
        let matched = false;
        for (const key of keys) {
            if (str.startsWith(key, i)) {
                result += pairs[key];
                i += key.length;
                matched = true;
                break;
            }
        }
        if (!matched) {
            result += str[i++];
        }
    }
    return result;
}
