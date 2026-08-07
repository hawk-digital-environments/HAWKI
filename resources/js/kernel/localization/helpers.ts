import type {ReplacementValue} from '$lib/kernel/localization/translator.js';
import {getHawkiApp} from '$lib/legacy/legacy.js';

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
 * @deprecated Use `useTranslator().translate()` instead, as this function will be removed in a future version.
 */
export function __(label: string, replacements?: ReplacementValue, ignoreMissing: boolean = false) {
    return getHawkiApp().localization.translator.translate(label, replacements, ignoreMissing);
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
 * @deprecated Use `useTranslator().getTranslations()` instead, as this function will be removed in a future version.
 */
export function getTranslations(label: string, replacements?: ReplacementValue, ignoreMissing: boolean = false): Record<string, any> | string | null {
    return getHawkiApp().localization.translator.getTranslations(label, replacements, ignoreMissing);
}

/**
 * Works similar to {@link getTranslations}, but instead of returning a nested object of translation labels,
 * it flattens the result into a single-level object with dot-notated keys.
 * Additionally, it ALWAYS expects your path to point to a nested object, and will throw an error if it points to a string or a non-object value.
 *
 * @param path
 * @deprecated Use `useTranslator().getTranslationsFlat()` instead, as this function will be removed in a future version.
 */
export function getTranslationsFlat(path: string): Record<string, string> {
    return getHawkiApp().localization.translator.getTranslationsFlat(path);
}

/**
 * Returns `true` when a translation entry exists for the given key.
 *
 * @param label - Translation key (supports dot notation).
 * @deprecated Use `useTranslator().hasLabel()` instead, as this function will be removed in a future version.
 */
export function hasTranslation(label: string) {
    return getHawkiApp().localization.translator.hasLabel(label);
}
