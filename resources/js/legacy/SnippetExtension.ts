import type {HawkiAppExtension, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {Component} from 'svelte';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        /**
         * @deprecated The snippets feature will be removed once the rewrite to an SPA is complete.
         */
        readonly snippets: WithoutAppExtensionInternals<SnippetExtension>;
    }
}

export type ComponentWithName = Component & { name?: string };

/**
 * @deprecated The snippets feature will be removed once the rewrite to an SPA is complete.
 */
export class SnippetExtension implements HawkiAppExtension {
    private snippets: Map<string, ComponentWithName> = new Map();

    public get all(): ComponentWithName[] {
        return Array.from(this.snippets.values());
    }

    public get(snippetName: string): Component | null {
        return this.snippets.get(snippetName) || null;
    }

    public register(snippetName: string, snippetComponent: Component): void {
        if (this.snippets.has(snippetName)) {
            console.warn(`Snippet with name '${snippetName}' is already registered. Overriding it.`);
        }
        Object.defineProperty(snippetComponent, 'name', {value: snippetName});
        this.snippets.set(snippetName, snippetComponent as ComponentWithName);
    }

    public provideProperties(): Record<string, any> {
        const extension = this;
        return {
            get snippets() {
                return extension;
            }
        };
    }

}
