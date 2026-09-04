import { useApp } from '$lib/app/hooks/useApp.svelte.js';
import type {WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {FavoritesExtension} from '$lib/kernel/favorites/FavoritesExtension.svelte.js';

/**
 * Hook that gives components access to the authenticated user's favorites
 * (`app.favorites`).
 *
 * Favorites are per-user server state addressed by the triple **namespace**
 * (default `'hawki-core'`), **type** (the kind of item, e.g. `'ai-model'`) and
 * **identifier** (the item's id) — see `FavoritesExtension` for details. The
 * list is fetched during the bootstrapper's `preparation` stage (after login)
 * and kept reactive, so `isFavorite` reflects confirmed server state.
 *
 * Writes (`markAsFavorite`/`removeAsFavorite`) are debounced per favorite key
 * and update the local state only after the server confirmed. **They reject on
 * failure** (e.g. favoriting while logged out) with a state-reflecting error —
 * catch and render a toast instead of logging a warning.
 *
 * @example
 * ```svelte
 * <script lang="ts">
 *     import {useFavorites} from '$lib/app/hooks/useFavorites.svelte.js';
 *     import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
 *
 *     const favorites = useFavorites();
 *     const toast = useToastContext();
 *     const isFavorite = $derived(favorites.isFavorite('ai-model', modelId));
 *
 *     async function toggleFavorite(modelId: string) {
 *         try {
 *             isFavorite
 *                 ? await favorites.removeAsFavorite('ai-model', modelId)
 *                 : await favorites.markAsFavorite('ai-model', modelId);
 *         } catch (error) {
 *             toast.error(error instanceof Error ? error.message : 'Saving the favorite failed.');
 *         }
 *     }
 * </script>
 * ```
 */
export function useFavorites(): WithoutAppExtensionInternals<FavoritesExtension> {
    return useApp().favorites;
}
