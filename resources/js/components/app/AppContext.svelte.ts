import type {ToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';

export class AppContext {
    public constructor() {
    }

    /**
     * Marker to detect if the "LegacySharedContent" snippet has been loaded on the page.
     * This is required to avoid having issues with the shared "Toaster" component.
     * An error will be thrown if the "LegacySharedContent" snippet is not loaded, and a toast is pushed.
     * @deprecated this a temporary workaround until we have a real single page app, and can use svelte contexts.
     */
    public legacySharedContentLoaded: boolean = false;

    /**
     * The shared toast context for the app
     * @deprecated this a temporary workaround until we have a real single page app, and can use svelte contexts.
     */
    public toastContext?: ToastContext;
}

// As long as we work with svelte, scattered around the page in separated snippets/mounts
// We use this app context to "simulate" a global context, that can be used in any snippet/mount.
// As soon as we migrate into a single page app, we can remove this, provide a real "createAppContext" function
// and use svelte's context API to provide the app context to all components.
const appContext = new AppContext();

/** Returns the current {@link AppContext}. Must be used within a component running {@link createAppContext}.
 * @throws Error If no app context is found.
 */
export function useAppContext(): AppContext {
    // @todo this is a temporary workaround until we have a real single page app, and can use svelte contexts.
    return appContext;
}

/** Creates a new {@link AppContext} and sets it in context. Should be used once in a parent component,
 * e.g. the main app component or layout.
 */
export function createAppContext(): AppContext {
    // @todo this is a temporary workaround until we have a real single page app, and can use svelte contexts.
    return appContext;
}
