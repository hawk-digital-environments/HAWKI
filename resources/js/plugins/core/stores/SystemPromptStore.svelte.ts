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
 * filtering `prompts` manually — the overload with `WellKnownSystemPromptType` is non-nullable,
 * so TypeScript won't require a null-check when using a known type constant.
 *
 * @example
 * import {useStore} from '$lib/app/hooks/useStore.svelte.js';
 * const systemPromptStore = useStore('system-prompts');
 * const chatPrompt = systemPromptStore.getPromptByType('chat');
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
     * The overload that accepts a `WellKnownSystemPromptType` returns `SystemPrompt`
     * (non-nullable); the string overload returns `SystemPrompt | null`. Prefer the
     * typed overload when using a known constant so callers skip the null-check.
     *
     * @todo this might break when the locale changes.
     */
    public getPromptByType(type: WellKnownSystemPromptType): SystemPrompt;
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
