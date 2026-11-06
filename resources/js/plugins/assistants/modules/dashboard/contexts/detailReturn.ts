/**
 * Where an assistant's detail page should return to when its back button is
 * pressed: the dashboard page the assistant was opened from.
 *
 * Remembered rather than delegated to `history.back()`, because the newest
 * history entry is not always the page the user came from — after a round
 * trip through the builder it is the builder.
 *
 * Plain module state, so a direct URL or a reload starts without an origin
 * and the detail page falls back to the store.
 */
let returnPath: string | null = null;
/** Scroll offset of the origin page's scroll region when the detail page was opened. */
let returnScroll = 0;
/** Set by the detail page's back button, so only a real return restores the scroll. */
let returning = false;

/** Records the page the detail page is being opened from, and how far it was scrolled. */
export function rememberDetailReturnPath(path: string, scrollTop = 0): void {
    returnPath = path;
    returnScroll = scrollTop;
    returning = false;
}

/** Marks that the detail page is navigating back to its origin page. */
export function markDetailReturn(): void {
    returning = true;
}

/**
 * The scroll offset to restore on `path`, once: only right after the detail
 * page's back button returned to it, otherwise null (e.g. the page reached
 * through the sidebar instead).
 */
export function takeDetailReturnScroll(path: string): number | null {
    if (!returning || path !== returnPath) return null;
    returning = false;
    return returnScroll;
}

/** The remembered origin page, or null when the detail page was entered without one. */
export function detailReturnPath(): string | null {
    return returnPath;
}
