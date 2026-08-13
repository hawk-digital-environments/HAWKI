import type {HawkiAppExtension, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {Component} from 'svelte';

/**
 * Declaration merging that exposes this extension on the app object as
 * `app.snippets` (see {@link HawkiAppExtension} / `createApp()` in
 * `kernel/HawkiApp.ts` — the keys returned by {@link SnippetExtension.provideProperties}
 * become real properties on the app).
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        /**
         * @deprecated The snippets feature will be removed once the rewrite to an SPA is complete.
         */
        readonly snippets: WithoutAppExtensionInternals<SnippetExtension>;
    }
}

/**
 * A Svelte {@link Component} carrying the name it was registered under.
 *
 * {@link SnippetExtension.register} stamps the registration name onto the
 * component's `name` property, so a component looked up from the registry can
 * be identified again later (useful for debugging which snippet a given DOM
 * node came from). Registration name === the `.svelte` file's base name.
 */
export type ComponentWithName = Component & { name?: string };

/**
 * # SnippetExtension — named registry of Svelte components the old UI can mount
 *
 * **Part of the transitional `legacy/` bridge.** HAWKI is migrating from a
 * Blade + vanilla-JS UI (`public/js/*.js`, `resources/views/**`) to a single-page
 * Svelte 5 app. This extension is scaffolding for that in-between state and is
 * expected to be deleted, not built upon.
 *
 * WHAT: a `Map<string, Component>` mounted onto the app object as
 * `app.snippets`. Nothing more — it holds components by name and hands them
 * out again.
 *
 * WHY it exists: the old UI has no module system and cannot `import` a Svelte
 * component. Instead a Blade template (or old JS) drops a
 * `<svelte-snippet type="ChatHeader">` element into the markup; the
 * `HTMLSvelteSnippetElement` custom element (see `svelteSnippetLoader.ts`)
 * then resolves that string through this registry and mounts the matching
 * component into the element. This registry is the string-to-component lookup
 * table that makes that indirection possible. In the finished SPA the
 * component tree will import its children directly and no registry is needed.
 *
 * WHEN to touch it: normally never. Registration happens in one place —
 * {@link legacyInitializeSnippetApps} (`$lib/legacy/legacyInitializeSnippetApps.js`)
 * lazy-globs `$lib/plugins/core/snippets/**\/*.svelte` and registers every
 * file under its base name — so adding a new snippet is just adding a
 * `.svelte` file to that folder, no manual `register()` call needed.
 *
 * @example
 * // Registering (what legacyInitializeSnippetApps does for every discovered file):
 * app.snippets.register('ChatHeader', ChatHeader);
 *
 * // Looking a snippet up (this is what the `<svelte-snippet>` element does):
 * const component = app.snippets.get('ChatHeader');
 *
 * @see HTMLSvelteSnippetElement in `$lib/legacy/svelteSnippetLoader.ts` — the consumer.
 * @deprecated The snippets feature will be removed once the rewrite to an SPA is complete.
 */
export class SnippetExtension implements HawkiAppExtension {
    /** Registered snippets, keyed by the name they were registered under. */
    private snippets: Map<string, ComponentWithName> = new Map();

    /**
     * All currently registered snippet components, in registration order.
     * Each carries its registration name on `.name` (see {@link ComponentWithName}).
     */
    public get all(): ComponentWithName[] {
        return Array.from(this.snippets.values());
    }

    /**
     * Looks up a snippet by the name it was registered under (for the core
     * plugin: the `.svelte` file's base name, e.g. `'ChatHeader'`).
     *
     * @returns the component, or `null` when no snippet with that name exists.
     *          Callers are expected to handle `null` — the name usually comes
     *          from a `type="..."` attribute written by hand in a Blade
     *          template, so typos are a realistic failure mode.
     */
    public get(snippetName: string): Component | null {
        return this.snippets.get(snippetName) || null;
    }

    /**
     * Registers a component under `snippetName`, making it mountable via
     * `<svelte-snippet type="snippetName">`.
     *
     * Re-registering an existing name overrides the previous component and
     * logs a warning — the registry deliberately does not throw, so a plugin
     * can intentionally replace a core snippet.
     *
     * Side effect: the component's `name` property is (re)defined to
     * `snippetName`, see {@link ComponentWithName}.
     */
    public register(snippetName: string, snippetComponent: Component): void {
        if (this.snippets.has(snippetName)) {
            console.warn(`Snippet with name '${snippetName}' is already registered. Overriding it.`);
        }
        Object.defineProperty(snippetComponent, 'name', {value: snippetName});
        this.snippets.set(snippetName, snippetComponent as ComponentWithName);
    }

    /**
     * {@link HawkiAppExtension} hook — exposes this instance as `app.snippets`.
     * Uses a getter so the app property always resolves to the live extension
     * instance rather than a snapshot.
     */
    public provideProperties(): Record<string, any> {
        const extension = this;
        return {
            get snippets() {
                return extension;
            }
        };
    }

}
