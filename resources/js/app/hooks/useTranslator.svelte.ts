import {useApp} from '$lib/app/hooks/useApp.svelte.js';

/**
 * Hook that gives components access to the app's i18n translator.
 *
 * Use this in any Svelte component that needs to render translated label
 * strings. It is a thin wrapper around `app.localization.translator`
 * (provided by `LocalizationExtension`) — it does not add its own reactivity,
 * but the underlying translator recomputes its output from the currently
 * loaded label set, so re-invoking `translate`/`__` after a locale switch
 * returns the new-locale text.
 *
 * The returned object is a plain object (not a class instance) so it can be
 * safely destructured — e.g. `const {__} = useTranslator();` — without losing
 * `this` binding.
 *
 * Returned methods:
 * - `translate(label, replacements?, ignoreMissing?)` / `__(...)` (alias) —
 *   resolve a translation key to its localized string. `replacements` is a
 *   `Record<string, string | ((content: string) => string)>` used to fill in
 *   `:key`-style placeholders and `<tag>content</tag>` callback replacements
 *   (Laravel-style). Returns `Missing translation: <label>` (or `''` if
 *   `ignoreMissing` is `true`) when the key is not found.
 * - `hasLabel(label)` — checks whether a translation key exists.
 * - `getTranslations(label, replacements?, ignoreMissing?)` — like
 *   `translate`, but can also return a nested object of translations
 *   (not just a leaf string).
 * - `getTranslationsFlat(path)` — resolves `path` to a nested translation
 *   object and flattens it into a single-level `Record<string, string>`;
 *   throws if the resolved value is not a nested object.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
 *
 *     const {__} = useTranslator();
 * </script>
 *
 * <textarea placeholder={__('chat.composer.textareaPlaceholder', {model: modelLabel})}></textarea>
 * ```
 */
export function useTranslator() {
    const app = useApp();
    return app.localization.translator;
}
