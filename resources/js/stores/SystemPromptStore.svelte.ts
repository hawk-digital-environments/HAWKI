import type {SystemPrompt, WellKnownSystemPromptType} from '$lib/schemas/resources/system-prompts.schema.js';
import {getResourceCollectionFromApi} from '$lib/data/api/api.js';
import {getConnection} from '$lib/data/connection/connection.js';

/**
 * Reactive store for system prompts configured on the server.
 *
 * Populated by {@link loadSystemPrompts} during bootstrap (authenticated connections only).
 * Use {@link getPromptByType} to retrieve a prompt by its well-known type string instead of
 * filtering `prompts` manually — the overload with `WellKnownSystemPromptType` is non-nullable,
 * so TypeScript won't require a null-check when using a known type constant.
 *
 * @example
 * import {systemPromptStore} from '$lib/stores/SystemPromptStore.svelte.js';
 * const chatPrompt = systemPromptStore.getPromptByType('chat');
 */
export class SystemPromptStore {
    /** All system prompts as returned by the API. */
    public prompts = $state([] as SystemPrompt[]);

    /**
     * Looks up a system prompt by its `prompt_type` string.
     *
     * The overload that accepts a `WellKnownSystemPromptType` returns `SystemPrompt`
     * (non-nullable); the string overload returns `SystemPrompt | null`. Prefer the
     * typed overload when using a known constant so callers skip the null-check.
     */
    public getPromptByType(type: WellKnownSystemPromptType): SystemPrompt;
    public getPromptByType(type: WellKnownSystemPromptType | string): SystemPrompt | null {
        return this.prompts.find(p => p.prompt_type === type) ?? null;
    }
}

export const systemPromptStore = new SystemPromptStore();

/**
 * Fetches all system prompts from the API and populates {@link systemPromptStore}.
 * Called during bootstrap. No-ops for unauthenticated connections.
 */
export async function loadSystemPrompts() {
    if (getConnection().type !== 'internal_authenticated') {
        return;
    }
    systemPromptStore.prompts = await getResourceCollectionFromApi('system-prompts');
}
