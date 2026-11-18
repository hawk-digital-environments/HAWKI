import { useApp } from '$lib/app/hooks/useApp.svelte.js';
import type { FetchCollectionQuery } from '$lib/kernel/api/buildQueryString.js';
import type { User } from '$plugins/core/schemas/resources/users.schema.js';
import { logApiError } from '$plugins/assistants/api/errors';

const USERS = 'users';

/** Mirror of the backend's search bounds (UserRepository::SEARCH_*). */
export const SEARCH_MIN_LENGTH = 3;
export const SEARCH_LIMIT = 5;

/** A user as offered by the share dialog's lookup (see {@link searchUsers}). */
export interface ShareUserCandidate {
    userId: number;
    displayName: string;
    username: string;
    avatar: string | null;
}

/**
 * The share dialog's user lookup: substring match against name, username
 * and email (matching happens server-side; email is never exposed to
 * non-admins).
 */
export async function searchUsers(query: string, signal?: AbortSignal): Promise<ShareUserCandidate[]> {
    const trimmed = query.trim();
    if (trimmed.length < SEARCH_MIN_LENGTH) return [];

    const request: FetchCollectionQuery = {
        filter: { search: trimmed }
    };

    try {
        const collection = await useApp().restApi.getResourceCollection(USERS, {
            query: request,
            signal
        });

        return Array.from(collection as User[]).map((user): ShareUserCandidate => ({
            userId: Number(user.id),
            displayName: user.display_name || user.username,
            username: user.username,
            avatar: user.avatar ?? null
        }));
    } catch (err) {
        throw logApiError('searchUsers', err, { query: trimmed });
    }
}
