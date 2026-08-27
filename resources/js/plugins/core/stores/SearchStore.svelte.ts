import type {DataStore} from '$lib/kernel/stores/types.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        search: SearchStore;
    }
}

/** One selectable row in the search palette. */
export interface SearchItem {
    /**
     * Stable identity, unique across *all* groups — namespace it with the
     * group's id, e.g. `core:chat.conversations/<slug>`. Titles may repeat;
     * ids may not. Duplicates are reported and dropped by `matchSearchGroups`.
     */
    id: string;
    /** Text shown in the row and matched against the query. */
    title: string;
    /** Leading icon; rendered with the palette's own size/stroke. */
    icon?: IconComponent;
    /** Extra terms the item is findable by. Never shown. */
    keywords?: string[];
    /** Runs after the palette has closed itself. */
    onSelect: () => void;
}

/**
 * A named collection of items contributed by a plugin or module. Both
 * `label` and `items` are getters, not values: the palette evaluates them
 * inside a `$derived`, so whatever reactive state they read (a store's list,
 * the current locale) keeps the palette current without the contributor
 * having to re-register anything.
 */
export interface SearchGroup {
    /** Unique across all groups, e.g. `core:chat.conversations`. */
    id: string;
    /** Translated heading shown above the group's rows. */
    label: () => string;
    /** The group's rows, in the order they should appear. */
    items: () => SearchItem[];
}

/** A group with its getters resolved and its rows narrowed to the query. */
export interface SearchGroupResult {
    id: string;
    label: string;
    items: SearchItem[];
}

/**
 * Registry behind `SearchDialog`: plugins and modules add groups here (usually
 * from the plugin's `ready()` hook, once the stores they read from exist), and
 * the dialog renders whatever is registered, in registration order.
 *
 * @example
 * app.stores.get('search').addGroup({
 *     id: 'core:chat.conversations',
 *     label: () => app.translator.translate('ui.search.conversations'),
 *     items: () => chatStore.conversations.map(c => ({
 *         id: `core:chat.conversations/${c.slug}`,
 *         title: c.name,
 *         icon: BubbleChatIcon,
 *         onSelect: () => app.router.goToRoute('chat.conversation', {slug: c.slug})
 *     }))
 * });
 */
export class SearchStore implements DataStore {
    public readonly name = 'search';

    private _groups = $state<SearchGroup[]>([]);

    public get groups(): readonly SearchGroup[] {
        return this._groups;
    }

    public addGroup(group: SearchGroup): void {
        if (this._groups.some(existing => existing.id === group.id)) {
            throw new Error(`Search group "${group.id}" is already registered.`);
        }
        this._groups = [...this._groups, group];
    }

    public removeGroup(id: string): void {
        this._groups = this._groups.filter(group => group.id !== id);
    }
}

/**
 * Case-insensitive substring match of the literal `query` against the item's
 * title and keywords. The query is taken as typed — surrounding whitespace
 * counts — so only an actually empty query matches every item.
 */
export function matchesSearchQuery(item: SearchItem, query: string): boolean {
    if (query === '') return true;
    const needle = query.toLocaleLowerCase();
    if (item.title.toLocaleLowerCase().includes(needle)) return true;
    return item.keywords?.some(keyword => keyword.toLocaleLowerCase().includes(needle)) ?? false;
}

/**
 * Resolves every group's live getters and keeps the rows matching `query`;
 * groups left without rows are dropped. Because items only exist once their
 * getter runs, this is also where identity is validated: an id that already
 * appeared (in this or an earlier group) is reported and the later row
 * skipped, so the palette never renders two rows with one identity.
 */
export function matchSearchGroups(groups: readonly SearchGroup[], query: string): SearchGroupResult[] {
    const seen = new Set<string>();
    const results: SearchGroupResult[] = [];

    for (const group of groups) {
        const items: SearchItem[] = [];
        for (const item of group.items()) {
            if (seen.has(item.id)) {
                console.error(`Search item id "${item.id}" in group "${group.id}" is not unique; row skipped.`);
                continue;
            }
            seen.add(item.id);
            if (matchesSearchQuery(item, query)) items.push(item);
        }
        if (items.length > 0) results.push({id: group.id, label: group.label(), items});
    }

    return results;
}
