import type {Translator} from '$lib/kernel/localization/translator.js';
import type {Locale} from '$lib/app/schemas/resources/compound/locales.schema.js';
import type {RouterHandle} from '$lib/components/ui/routing/index.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';
import type {Component} from 'svelte';

/**
 * Sidebar hook points, declared by the core shell that owns these surfaces.
 * The collecting components apply them inside a `$derived` (see
 * `useSidebarHooks.svelte.ts`); plugins register filter handlers from their
 * `hooks()` lifecycle method — each receives the accumulated entries and
 * returns the updated list, so a plugin may append its own entries or
 * remove/reorder other plugins' (e.g. for whitelabel builds).
 *
 * Handler execution order is set at registration time via the registrar's
 * `order` option; ties keep plugin registration order (core first).
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiHooks {
        moduleSelectorEntries: {value: ModuleSelectorEntry[]; ctx: SidebarContext};
        sidebarSlots: {value: SidebarSlotEntry[]; ctx: SidebarContext};
    }
}

/**
 * Everything a hook handler may need to localize and route an entry.
 * Constructed by the applying component at apply (or click) time, so reads
 * of `locale`/`router` state made through it are tracked by the applying
 * `$derived` and re-apply the hook on change.
 */
export interface SidebarContext {
    locale: Locale;
    translate: Translator['translate'];
    router: RouterHandle;
}

/**
 * One row in the module selector's command palette. Declares its own label
 * (already localized via `ctx.translate`), icon, navigation behavior, and
 * whether the module it leads to is the one currently shown.
 */
export interface ModuleSelectorEntry {
    /** Stable key, e.g. `'core:chat'` — palette value and "current" matching. */
    id: string;
    /** Already localized by the handler via `ctx.translate`. */
    label: string;
    /** Component icon, or an image URL string (the palette skips those). */
    icon?: string | IconComponent | Component;
    onSelect: (ctx: SidebarContext) => void;
    /** Computed by the handler from `ctx.router`; the selector just displays it. */
    active: boolean;
}

/**
 * A component injected into a slot of the app sidebar. `panel` entries fill
 * the module-sidebar area (the route-driven main content of the sidebar);
 * `header`/`footer` entries render in the sidebar's chrome areas; `action`
 * entries render in the pinned action area directly above the profile
 * footer — the active module's primary call to action (e.g. chat's
 * "New Chat" button, assistants' "Erstellen").
 */
export interface SidebarSlotEntry {
    id: string;
    position: 'panel' | 'header' | 'footer' | 'action';
    component: Component;
    active: boolean;
}
