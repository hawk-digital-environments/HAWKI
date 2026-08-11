/**
 * # svelte-snippet — custom HTML element loader
 *
 * Registers the `<svelte-snippet>` custom element, which lets you mount any
 * Svelte component from the `resources/js/svelte/snippets/` folder directly
 * in a Blade template (or any plain HTML) without writing any JavaScript.
 *
 * ## Usage in Blade templates (recommended)
 *
 * Use the `<x-svelte>` Blade component — it handles JSON-encoding and
 * HTML-escaping for you and forwards any extra HTML attributes unchanged:
 *
 * ```blade
 * {{-- minimal --}}
 * <x-svelte type="InputModelSelector" />
 *
 * {{-- with props and extra HTML attributes --}}
 * <x-svelte
 *     type="InputModelSelector"
 *     :props="['chatId' => 42, 'readonly' => true]"
 *     class="my-class"
 * />
 * ```
 *
 * See `app/Services/Frontend/Connection/View/SvelteComponent.php` for the
 * Blade component implementation.
 *
 * ## Usage in plain HTML / JavaScript
 *
 * When you need to place the element outside of a Blade context, write the
 * custom element directly. The `props` attribute must be a JSON string:
 *
 * ```html
 * <svelte-snippet
 *     type="InputModelSelector"
 *     props='{"chatId": 42, "readonly": true}'
 * ></svelte-snippet>
 * ```
 *
 * ## Receiving props in the Svelte component
 *
 * ```svelte
 * <script lang="ts">
 *     interface Props { chatId: number; readonly: boolean; }
 *     const { chatId, readonly }: Props = $props();
 * </script>
 * ```
 *
 * The "root" prop is always included and contains a reference to the root element itself,
 * so you can use it to read other attributes or manipulate the element from within the component if needed.
 *
 * ## Adding a new snippet
 *
 * 1. Create a `.svelte` file in `resources/js/svelte/snippets/`, e.g.
 *    `resources/js/svelte/snippets/MyWidget.svelte`.
 * 2. Use it in Blade: `<x-svelte type="MyWidget" />`.
 *
 * No registration or import is needed — the loader discovers all files in
 * that folder automatically at build time.
 *
 * ## Reactivity
 *
 * When the `type` or `props` attribute changes at runtime, the component is
 * destroyed and remounted with the new values. Any internal component state
 * is reset on each remount, so treat the element as stateless from the
 * outside. Other attributes (e.g. `class`, `id`) are ignored by the loader.
 *
 * ## Lifecycle
 *
 * - Element added to DOM → component is mounted.
 * - `type` or `props` attribute changes → component is destroyed and remounted.
 * - Element removed from DOM → component is destroyed and cleaned up.
 */

import {mount, unmount} from 'svelte';
import {getHawkiApp} from '$lib/legacy/legacy.js';

/**
 * @deprecated Will be removed as soon as all the old js has been refactored
 */
export class HTMLSvelteSnippetElement extends HTMLElement {
    static get observedAttributes(): string[] {
        return ['type', 'props'];
    }

    /** The currently mounted Svelte app instance, or null when unmounted. */
    private _app: object | null = null;

    private _hasActiveDestruction: boolean = false;
    private _mountAfterDestruction: boolean = false;

    private _oldType: string | null = null;
    private _oldProps: string | null = null;

    /**
     * Monotonically-increasing counter used to cancel in-flight async mounts.
     * Incremented on every destroy so that a pending import whose component
     * was already removed will be a no-op when it finally resolves.
     */
    private _mountId: number = 0;

    connectedCallback(): void {
        if (this._hasActiveDestruction) {
            this._mountAfterDestruction = true;
            return;
        }
        this._mountComponent();
    }

    disconnectedCallback(): void {
        this._mountAfterDestruction = false;
        this._destroyComponent();
    }

    attributeChangedCallback(): void {
        const newType = this.getAttribute('type');
        const newProps = this.getAttribute('props');
        if (newType === this._oldType && newProps === this._oldProps) {
            return; // No change in relevant attributes, skip remounting
        }
        this._oldType = newType;
        this._oldProps = newProps;
        this._mountAfterDestruction = true;
        this._destroyComponent();
        this._mountComponent();
    }

    /**
     * Updates the "props" attribute with the given props object.
     * By default, merges the new props with the existing ones; pass `replace: true` to overwrite them instead.
     * @param props The props to set, as a plain object. Will be JSON-stringified and set as the "props" attribute.
     * @param replace If true, the new props will replace the existing ones instead of merging with them.
     */
    public setProps(props: Record<string, unknown>, replace?: boolean): void {
        const currentProps = this._getProps();
        delete currentProps.root; // The "root" prop is reserved and always reflects the element itself, so it shouldn't be merged or replaced by user input.
        const newProps = replace ? props : {...currentProps, ...props};
        this.setAttribute('props', JSON.stringify(newProps));
    }

    /**
     * Parses the "props" attribute as JSON and returns the result.
     * Returns an empty object if the attribute is absent or invalid.
     */
    private _getProps(): Record<string, unknown> {
        const props = (() => {
            const raw = this.getAttribute('props');
            if (!raw) return {};
            try {
                return JSON.parse(raw) as Record<string, unknown>;
            } catch (e) {
                console.error('<svelte-snippet>: The "props" attribute is not valid JSON.', e);
                return {};
            }
        })();
        return {...props, root: this}; // Always include the root element as a prop for convenience
    }

    private _mountComponent(): void {
        const type = this.getAttribute('type');
        if (!type) {
            console.error('<svelte-snippet>: The required "type" attribute is missing.');
            return;
        }

        const app = getHawkiApp();
        const component = app.snippets.get(type);

        if (!component) {
            console.error(
                `<svelte-snippet>: No snippet found for type "${type}".`
            );
            return;
        }

        this.innerHTML = ''; // Clear any leftover content from the previous mount
        this._app = mount(component, {
            target: this,
            props: this._getProps()
        });
    }

    private async _destroyComponent(): Promise<void> {
        if (!this._hasActiveDestruction) {
            if (this._app) {
                await unmount(this._app);
                this._app = null;
            }
        }

        if (this._mountAfterDestruction) {
            this._mountAfterDestruction = false;
            this._mountComponent();
        }
    }
}
