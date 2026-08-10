import type {HawkiAppExtension, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {ToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';

/**
 * Declaration merging that exposes this extension on the app object as
 * `app.toast` (see {@link HawkiAppExtension} / `createApp()` in
 * `kernel/HawkiApp.ts`).
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        /**
         * @deprecated The toast context will only be provided via sveltes context feature once the rewrite to an SPA is complete.
         */
        readonly toast: WithoutAppExtensionInternals<LegacyToastExtension>;
    }
}

/**
 * # LegacyToastExtension — app-global holder for the single {@link ToastContext}
 *
 * **Part of the transitional `legacy/` bridge.** It exists purely because the
 * page is not (yet) a single Svelte component tree, and it is meant to be
 * deleted once it is.
 *
 * WHAT: a one-slot container. It stores the app-wide {@link ToastContext}
 * instance and re-exposes it on the app object as `app.toast`.
 *
 * WHY it exists: toasts are rendered by a single `Toaster` component (mounted
 * once via the `LegacySharedContent` snippet), while they are raised from all
 * over the app — including from several *disconnected* Svelte roots, since
 * during the migration each `<svelte-snippet>` mounts its own component tree
 * into the legacy Blade markup. Those roots share no common ancestor, so
 * Svelte's `createContext`/`getContext` cannot reach across them. Parking the
 * one shared instance on the app object is the workaround. In the finished
 * SPA there will be one root layout that calls `createToastContext()`, and
 * `useToastContext()` will resolve through Svelte's own context — at which
 * point this extension can go away.
 *
 * WHEN to use it: **don't, directly.** Call `useToastContext()` from
 * `$lib/components/ui/toast/ToastContext.svelte.js` instead — it already
 * prefers the real Svelte context and only falls back to this bridge (lazily
 * creating and registering the instance here on first use).
 *
 * @example
 * // Preferred — indirection through the toast helper:
 * import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
 * useToastContext().error('Upload fehlgeschlagen.');
 *
 * @see ToastContext in `$lib/components/ui/toast/ToastContext.svelte.js`
 * @deprecated The toast context will only be provided via sveltes context feature once the rewrite to an SPA is complete.
 */
export class LegacyToastExtension implements HawkiAppExtension {
    /** The shared toast context, or `null` until {@link setContext} has been called. */
    private _context: ToastContext | null = null;

    /**
     * The shared {@link ToastContext}.
     *
     * @throws Error when no context has been registered yet — that means
     *         something tried to raise a toast before any component created
     *         the context. Use `useToastContext()`, which handles this case by
     *         creating the instance on demand.
     */
    public get context(): ToastContext {
        if (!this._context) {
            throw new Error('Toast context is not set. Make sure to set it before using it.');
        }
        return this._context;
    }

    /**
     * Registers the app-wide toast context. Called by `createToastContext()` /
     * the lazy fallback inside `useToastContext()` — not something feature code
     * should call itself. A later call silently replaces the previous instance,
     * which would orphan any toasts still queued in the old one.
     */
    public setContext(context: ToastContext): void {
        this._context = context;
    }

    /**
     * {@link HawkiAppExtension} hook — exposes this instance as `app.toast`.
     * Uses a getter so the app property always resolves to the live extension
     * instance rather than a snapshot.
     */
    public provideProperties(): Record<string, any> {
        const extension = this;
        return {
            get toast() {
                return extension;
            }
        };
    }
}
