/**
 * Where the assistant builder should return to when it is left.
 *
 * Remembered right before navigating *into* the builder (see
 * `requestBuilderIntent`) and read on the way out, so "Zurück" lands the user
 * back on the page they came from — an assistant's detail page, the drafts
 * list — instead of a fixed dashboard section.
 *
 * Session storage rather than state on `BuilderContext`, and its own module
 * rather than living next to that context, because the two readers sit on
 * opposite sides of the builder: the sidebar's "Zurück" row is part of the
 * *app* sidebar, outside the builder layout's subtree, so it can reach
 * neither the context nor (without dragging the builder's whole dependency
 * graph into the sidebar's bundle) the module that defines it.
 */
const RETURN_STORAGE_KEY = 'assistant_builder_return';

/**
 * Records the page the builder is being opened from. Passing `null` clears
 * the stored path — an entry point with no origin to return to (the sidebar's
 * "Erstellen") must not inherit the one a previous session left behind.
 */
export function rememberBuilderReturnPath(path: string | null): void {
    if (path) {
        sessionStorage.setItem(RETURN_STORAGE_KEY, path);
    } else {
        sessionStorage.removeItem(RETURN_STORAGE_KEY);
    }
}

/** The remembered origin page, or null when the builder was entered without one. */
export function builderReturnPath(): string | null {
    return sessionStorage.getItem(RETURN_STORAGE_KEY);
}

/** Forgets the remembered origin — called once the builder has been left. */
export function clearBuilderReturnPath(): void {
    sessionStorage.removeItem(RETURN_STORAGE_KEY);
}
