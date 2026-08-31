import type {DataStore} from '$lib/kernel/stores/types.js';
import {
    type SystemPrompt,
    type WellKnownSystemPromptType,
    WellKnownSystemPromptTypes
} from '$plugins/core/schemas/resources/system-prompts.schema.js';
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
 * filtering `prompts` manually. Well-known types are part of the server's
 * required configuration and fail loudly if that invariant is broken.
 *
 * @example
 * import {useStore} from '$lib/app/hooks/useStore.svelte.js';
 * const systemPromptStore = useStore('system-prompts');
 * const chatPrompt = systemPromptStore.getPromptByType('default').prompt;
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
     * @todo this might break when the locale changes.
     */
    public getPromptByType(type: WellKnownSystemPromptType): SystemPrompt;
    public getPromptByType(type: string): SystemPrompt | null;
    public getPromptByType(type: WellKnownSystemPromptType | string): SystemPrompt | null {
        const prompt = this.promptsByType.get(type);
        if (!prompt && WellKnownSystemPromptTypes.includes(type as WellKnownSystemPromptType)) {
            throw new Error(`Required system prompt "${type}" is unavailable.`);
        }
        return prompt ?? null;
    }

    public async loadData(app: HawkiApp) {
        if (!app.connection.isAuthenticated) {
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
