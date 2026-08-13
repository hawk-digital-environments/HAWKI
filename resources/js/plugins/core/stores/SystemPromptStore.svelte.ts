import type {DataStore} from '$lib/kernel/stores/types.js';
import type {SystemPrompt, WellKnownSystemPromptType} from '$plugins/core/schemas/resources/system-prompts.schema.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'system-prompts': SystemPromptStore;
    }
}

/**
 * Reactive store for system prompts configured on the server.
 *
 * Populated by {@link SystemPromptStore.loadData} during bootstrap (authenticated connections only).
 * Use {@link getPromptByType} to retrieve a prompt by its well-known type string instead of
 * filtering `prompts` manually. Even well-known types can be absent (the server syncs them
 * from its config via `ai:config:sync`), so callers must handle `null`.
 *
 * @example
 * import {useStore} from '$lib/app/hooks/useStore.svelte.js';
 * const systemPromptStore = useStore('system-prompts');
 * const chatPrompt = systemPromptStore.getPromptByType('chat')?.prompt ?? '';
 */
export class SystemPromptStore implements DataStore {
    public readonly name = 'system-prompts';

    /** All system prompts as returned by the API. */
    public prompts = $state([] as SystemPrompt[]);

    public promptsByType = $derived.by(() => {
        const map = new Map<string, SystemPrompt>();
        for (const prompt of this.prompts) {
            map.set(prompt.prompt_type, prompt);
        }
        return map;
    });

    /**
     * Looks up a system prompt by its `prompt_type` string.
     *
     * Returns `null` when no prompt of that type exists — this can happen even
     * for well-known types when the server has not synced its prompt config
     * yet, so callers must provide a fallback.
     *
     * @todo this might break when the locale changes.
     */
    public getPromptByType(type: WellKnownSystemPromptType | string): SystemPrompt | null {
        return this.promptsByType.get(type) ?? null;
    }

    public async loadData(app: HawkiApp) {
        try {
            app.authenticatedConnection;
        } catch {
            return;
        }

        return new Promise<void>(resolve => {
            $effect.root(() => {
                $effect(() => {
                    (async () => {
                        this.prompts = await app.restApi.getResourceCollection(
                            'system-prompts',
                            {
                                locale: app.localization.locale
                            }
                        );
                        resolve();
                    })();
                });
            });
        });
    }
}
