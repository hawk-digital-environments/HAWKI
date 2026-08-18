import {createContext} from 'svelte';

/**
 * Placeholder replacements for {@link TranslatorInterface.translate}. Mirrors
 * the host app's replacement semantics (Laravel-style `:key` substitution and
 * `<tag>content</tag>` callback replacement) closely enough for this
 * package's own key set, without depending on the host's translator types.
 */
export type TranslationReplacements = Record<string, string | ((content: string) => string)>;

/**
 * Translator surface this package's components need. Deliberately narrow —
 * only `translate`/`__` and `getTranslationsFlat` are used by any component
 * in this directory (`Dialog`, `ConfirmDialog`, `InfoDialog`, `BottomSheet`,
 * `StatusDot`, `CitationList`, `CitationReference`, `DropdownMenuSwitchItem`,
 * `UrlPreviewTooltip`, `Markdown`). A host's richer translator (e.g. HAWKI's
 * own, which also has `hasLabel`/`getTranslations`) satisfies this interface
 * structurally without any adapter code.
 */
export interface TranslatorInterface {
    /**
     * Resolves `label` to its localized string, substituting `replacements`
     * (`:key`-style placeholders and `<tag>content</tag>` callbacks).
     */
    translate(label: string, replacements?: TranslationReplacements, ignoreMissing?: boolean): string;

    /** Alias for {@link translate}, for `__('some.key')`-style call sites. */
    __(label: string, replacements?: TranslationReplacements, ignoreMissing?: boolean): string;

    /**
     * Resolves `path` to a nested translation object and flattens it into a
     * single-level `Record<string, string>`. Used by `Markdown` to seed
     * `markstream-svelte`'s i18n map.
     */
    getTranslationsFlat(path: string): Record<string, string>;
}

const [get, set] = createContext<TranslatorInterface>();

/**
 * Returned by {@link useTranslator} when no ancestor called
 * {@link provideTranslator} — an identity translator, so every component in
 * this package still renders sensibly standalone (e.g. outside the host app,
 * in a story/playground): `translate`/`__` return the key itself rather than
 * a "Missing translation: ..." message, and `getTranslationsFlat` returns an
 * empty object (safe for `Markdown`, since `markstream-svelte` already has
 * its own built-in default strings and humanizes unknown keys).
 */
const standaloneTranslator: TranslatorInterface = {
    translate: (label) => label,
    __: (label) => label,
    getTranslationsFlat: () => ({})
};

/**
 * Publishes the host's translator to the component subtree. Call once,
 * during component initialization (a host app's root layout / every Svelte
 * mount root), passing a getter so a locale switch — which re-resolves the
 * host translator against the currently loaded label set — keeps rendering
 * live text through this context boundary rather than a frozen snapshot
 * taken when the context was created.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {provideTranslator} from '@hawk-hhg/hawki-svelte-components';
 *     import {useApp} from '$lib/app/hooks/useApp.svelte.js';
 *
 *     const app = useApp();
 *     provideTranslator(() => app.localization.translator);
 * </script>
 * ```
 */
export function provideTranslator(getter: () => TranslatorInterface): void {
    set({
        translate: (label, replacements, ignoreMissing) => getter().translate(label, replacements, ignoreMissing),
        __: (label, replacements, ignoreMissing) => getter().translate(label, replacements, ignoreMissing),
        getTranslationsFlat: (path) => getter().getTranslationsFlat(path)
    });
}

/**
 * Reads the {@link TranslatorInterface} published by an ancestor
 * {@link provideTranslator} call. Falls back to an identity translator when
 * no host provided one. The returned object's methods are safe to
 * destructure (`const {__} = useTranslator();`) — they don't rely on `this`.
 */
export function useTranslator(): TranslatorInterface {
    try {
        return get();
    } catch {
        return standaloneTranslator;
    }
}
