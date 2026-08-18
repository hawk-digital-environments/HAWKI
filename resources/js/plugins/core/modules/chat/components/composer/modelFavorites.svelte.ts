const STORAGE_KEY = 'hawkiFavoriteModels';

let ids = $state<string[]>(readPersisted());

function readPersisted(): string[] {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed.filter(value => typeof value === 'string') : [];
    } catch (e) {
        // Storage unavailable or corrupt value — start without favorites.
        return [];
    }
}

function persist(): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    } catch (e) {
        // Storage may be unavailable (private mode/quota) — favorites still work for this page.
    }
}

/**
 * Reactive set of favorited `model_id`s for the model picker, persisted in
 * localStorage. Module-level singleton so the desktop popover and the mobile
 * sheet share the same state without a kernel store registration.
 *
 * Stale ids of models that no longer exist are harmless — consumers filter
 * against the live `ai-models` store.
 */
export const modelFavorites = {
    get ids(): string[] {
        return ids;
    },
    has(modelId: string): boolean {
        return ids.includes(modelId);
    },
    toggle(modelId: string): void {
        ids = ids.includes(modelId) ? ids.filter(id => id !== modelId) : [...ids, modelId];
        persist();
    }
};
