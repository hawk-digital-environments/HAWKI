/**
 * Reactive context for transient toast notifications.
 *
 * Toasts are rendered by a single {@link Toaster} component mounted near the
 * chat input. Push one from anywhere via the context functions.
 *
 * @example
 * const toastContext = useToastContext();
 * toastContext.error('Datei konnte nicht angehängt werden.');
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

export class ToastContext {
    /** Currently visible toasts, oldest first. */
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

    public error(message: string, duration?: number): number {
        return this.push(message, 'error', duration);
    }

    public success(message: string, duration?: number): number {
        return this.push(message, 'success', duration);
    }

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
 * Returns the current {@link ToastContext} from context. Must be used within a component running {@link createToastContext}.
 * @throws Error If no toast context is found.
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
