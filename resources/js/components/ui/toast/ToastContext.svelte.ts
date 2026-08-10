/**
 * Reactive context for transient toast notifications ("snackbars").
 *
 * WHY: any component that needs to surface a transient success/error/info
 * message (e.g. a failed file upload, a saved setting) should not need to
 * know where the toast pile is rendered. This context decouples "who raises
 * a toast" from "who renders it": call {@link useToastContext} to get the
 * instance and push a message; a single {@link Toaster} component (mounted
 * once, e.g. via `LegacySharedContent.svelte`) reads `toasts` and renders
 * the stack.
 *
 * HOW TO USE — from any component or plain `.ts` module:
 * ```ts
 * import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
 *
 * const toastContext = useToastContext();
 * toastContext.error('Datei konnte nicht angehängt werden.');
 * toastContext.success('Gespeichert.');
 * toastContext.info('Hinweis: ...', 8000); // custom duration in ms
 * ```
 *
 * Wiring: normally you don't need to call {@link createToastContext} or
 * {@link ToastContext} yourself — `useToastContext()` lazily creates and
 * registers an instance via the legacy `useApp().toast` bridge the first
 * time it's called without an existing context. `createToastContext()` is
 * only for a real Svelte-context-provider setup (a root layout component
 * calling it once so descendants share one instance via `svelte`'s
 * `createContext`); today the app still lives in `useApp().toast` because
 * we render disconnected "snippets" rather than one single-page app tree
 * — see {@link LegacySharedContent.svelte}.
 */
import {createContext} from 'svelte';
import {useApp} from '$lib/app/hooks/useApp.svelte.js';

export type ToastVariant = 'error' | 'success' | 'info';

export interface Toast {
    id: number;
    message: string;
    variant: ToastVariant;
}

/** How long a toast stays on screen before auto-dismissing. */
const DEFAULT_DURATION = 5000;

/**
 * Holds the reactive list of currently-visible toasts and schedules their
 * auto-dismissal. One instance is shared app-wide (see module doc above);
 * don't construct this directly — use {@link useToastContext} to obtain the
 * shared instance, or {@link createToastContext} to set up a new one.
 */
export class ToastContext {
    /** Currently visible toasts, oldest first. Read by {@link Toaster} to render the stack. */
    public toasts = $state<Toast[]>([]);

    private nextId = 0;
    private timers = new Map<number, ReturnType<typeof setTimeout>>();
    /** Remaining ms for each toast while paused; absent when running. */
    private remaining = new Map<number, number>();
    /** When the current pause started (for computing elapsed time on resume). */
    private pausedAt: number | null = null;

    /** Shows a toast and schedules its auto-dismissal. */
    public push(message: string, variant: ToastVariant = 'info', duration = DEFAULT_DURATION): number {
        const id = this.nextId++;
        this.toasts = [...this.toasts, {id, message, variant}];
        if (this.pausedAt !== null) {
            // Stack is hovered — store the full duration for later.
            this.remaining.set(id, duration);
        } else {
            this.timers.set(id, setTimeout(() => this.dismiss(id), duration));
        }
        return id;
    }

    /** Pauses auto-dismissal for all toasts (e.g. while hovered). */
    public pause(): void {
        if (this.pausedAt !== null) return;
        this.pausedAt = Date.now();
        for (const [id, timer] of this.timers) {
            clearTimeout(timer);
            // We don't know the original deadline, so store a sentinel; resume
            // will use DEFAULT_DURATION as a safe fallback for these toasts.
            if (!this.remaining.has(id)) {
                this.remaining.set(id, DEFAULT_DURATION);
            }
        }
        this.timers.clear();
    }

    /** Resumes auto-dismissal, subtracting time already spent on screen. */
    public resume(): void {
        if (this.pausedAt === null) return;
        const elapsed = Date.now() - this.pausedAt;
        this.pausedAt = null;
        for (const [id, rem] of this.remaining) {
            const left = Math.max(0, rem - elapsed);
            this.timers.set(id, setTimeout(() => this.dismiss(id), left));
        }
        this.remaining.clear();
    }

    /** Shortcut for {@link push} with `variant: 'error'`. */
    public error(message: string, duration?: number): number {
        return this.push(message, 'error', duration);
    }

    /** Shortcut for {@link push} with `variant: 'success'`. */
    public success(message: string, duration?: number): number {
        return this.push(message, 'success', duration);
    }

    /** Shortcut for {@link push} with `variant: 'info'`. */
    public info(message: string, duration?: number): number {
        return this.push(message, 'info', duration);
    }

    /** Removes a toast by id and clears its pending timer. */
    public dismiss(id: number): void {
        const timer = this.timers.get(id);
        if (timer !== undefined) {
            clearTimeout(timer);
            this.timers.delete(id);
        }
        this.toasts = this.toasts.filter(t => t.id !== id);
    }
}

const [get, set] = createContext<ToastContext>();

/**
 * Returns the shared {@link ToastContext} — the one function callers should
 * reach for to push a toast (see the module doc above for a usage example).
 * Never throws: it first looks for a real Svelte context (set up via
 * {@link createToastContext}); if none is found it falls back to the legacy
 * `useApp().toast` bridge, lazily creating and registering an instance
 * there on first use so today's disconnected "snippet" pages still share a
 * single toast pile.
 */
export function useToastContext(): ToastContext {
    try {
        const context = get();
        if (context) {
            return context;
        }
    } catch (e) {

    }


    // @todo remove this once we have a real single page app, and can use svelte contexts.
    try {
        return useApp().toast.context;
    } catch (e) {
        const context = new ToastContext();
        useApp().toast.setContext(context);
        return context;
    }
}

/** Creates a new {@link ToastContext} and sets it in context. Should be used once in a parent component,
 * e.g. the main app component or layout.
 */
export function createToastContext() {
    const context = new ToastContext();
    set(context);
    useApp().toast.setContext(context);
    return context;
}
