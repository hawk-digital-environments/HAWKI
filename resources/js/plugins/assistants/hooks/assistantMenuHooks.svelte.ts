import {useApp} from '$lib/app/hooks/useApp.svelte.js';
import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
import {useRouter} from '$lib/components/ui/routing/index.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {RouterHandle} from '$lib/components/ui/routing/index.js';
import type {SidebarContext} from '$lib/app/ui/sidebarHooks.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';
import type {Component} from 'svelte';

/**
 * Collector hook for the assistants sidebar's nav rows, owned by the
 * assistants plugin (which owns that surface): the `AssistantsSidebar`
 * component applies it inside a `$derived`, and every registered handler
 * filters the accumulated entries. The assistants plugin registers the
 * standard dashboard/builder rows; any other plugin may register a handler
 * to add — or reshape — rows in that menu.
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiHooks {
        assistantMenuEntries: {value: AssistantMenuEntry[]; ctx: SidebarContext};
    }
}

/** One nav row in the assistants sidebar. */
export interface AssistantMenuEntry {
    id: string;
    /** The sidebar's two drill levels; the active route decides which shows. */
    level: 'dashboard' | 'builder';
    label: string;
    icon?: IconComponent | Component;
    /** Named route to navigate to (alternative to `onSelect`). */
    route?: string;
    onSelect?: (ctx: SidebarContext) => void;
    active?: boolean;
    /** Fully custom row: rendered instead of the default `SidebarItem`. */
    component?: Component;
}

/**
 * Applies the `assistantMenuEntries` hook as a class-field `$derived` —
 * the only legal `$derived` placement inside a `.svelte.ts` file. Same
 * reactivity contract as the core collectors (see
 * `useSidebarHooks.svelte.ts`): route changes and locale switches re-apply.
 */
export class AssistantMenu {
    readonly entries = $derived.by(() => {
        const ctx = {
            locale: this.app.localization.locale,
            translate: this.translate,
            router: this.router
        } satisfies SidebarContext;
        return this.app.hooks.apply('assistantMenuEntries', [] as AssistantMenuEntry[], ctx);
    });

    constructor(
        private readonly app: HawkiApp,
        private readonly translate: Translator['translate'],
        private readonly router: RouterHandle
    ) {}
}

export function useAssistantMenuEntries(): AssistantMenu {
    return new AssistantMenu(useApp(), useTranslator().translate, useRouter());
}
