/**
 * Reads the assistant browser's shareable URL state (`?q=…&category=…`) at
 * page entry, so a page can seed its `searchQuery`/`activeFilters` state and
 * fire exactly one filtered initial request — no "load everything, then
 * reload filtered" round trip.
 *
 * Writing the URL back is the mirror job of AssistantBrowser's URL-sync
 * effect; the page number is intentionally not restored here (the list always
 * starts on page 1).
 */
export function readBrowserUrlState(): {query: string, categories: Set<string>} {
    const params = new URLSearchParams(window.location.search);
    const query = params.get('q')?.trim() ?? '';
    const category = params.get('category');
    const categories = new Set(category ? category.split(',').filter(Boolean) : []);
    return {query, categories};
}
