import {useApp} from '$lib/app/hooks/useApp.svelte.js';
import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
import {useRouter} from '$lib/components/ui/routing/index.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {RouterHandle} from '$lib/components/ui/routing/index.js';
import type {ModuleSelectorEntry, SidebarContext, SidebarSlotEntry} from '$lib/app/ui/sidebarHooks.js';

/**
 * Fresh context for click-time callbacks (`entry.onSelect(ctx)`), built from
 * the calling component's hooks. Unlike the apply-time context this is not
 * part of a `$derived`, so it simply snapshots the current locale.
 */
export function useSidebarContext(): SidebarContext {
    const app = useApp();
    const {translate} = useTranslator();
    const router = useRouter();
    return {locale: app.localization.locale, translate, router};
}

/**
 * Applies the `moduleSelectorEntries` hook as a class-field `$derived` —
 * the only legal `$derived` placement inside a `.svelte.ts` file (see
 * `SidebarState`/`RouterState` for the same class idiom). Reading
 * `entries` applies every registered handler; the `ctx.router`/`locale`
 * reads the handlers make are tracked, so route changes and locale switches
 * re-apply the hook.
 */
export class ModuleSelectorEntries {
    readonly entries = $derived.by(() => {
        const ctx = {
            locale: this.app.localization.locale,
            translate: this.translate,
            router: this.router
        } satisfies SidebarContext;
        return this.app.hooks.apply('moduleSelectorEntries', [] as ModuleSelectorEntry[], ctx);
    });

    constructor(
        private readonly app: HawkiApp,
        private readonly translate: Translator['translate'],
        private readonly router: RouterHandle
    ) {}
}

/** Applies the `sidebarSlots` hook. Same reactivity contract as {@link ModuleSelectorEntries}. */
export class SidebarSlots {
    readonly entries = $derived.by(() => {
        const ctx = {
            locale: this.app.localization.locale,
            translate: this.translate,
            router: this.router
        } satisfies SidebarContext;
        return this.app.hooks.apply('sidebarSlots', [] as SidebarSlotEntry[], ctx);
    });

    constructor(
        private readonly app: HawkiApp,
        private readonly translate: Translator['translate'],
        private readonly router: RouterHandle
    ) {}
}

export function useModuleSelectorEntries(): ModuleSelectorEntries {
    return new ModuleSelectorEntries(useApp(), useTranslator().translate, useRouter());
}

export function useSidebarSlots(): SidebarSlots {
    return new SidebarSlots(useApp(), useTranslator().translate, useRouter());
}
