import type {HawkiAppExtension, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';
import {count, create, insert, search, type Orama} from '@orama/orama';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        search: WithoutAppExtensionInternals<SearchExtension>;
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
 * inside a `$derived`, so whatever reactive (`$state`) data they read — a
 * store's list, the current locale — keeps the palette current without the
 * contributor having to re-register or "update" anything. A group backed by
 * plain, non-reactive data is a snapshot; wrap that data in `$state` (or
 * remove and re-add the group) if it must change.
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
 * App extension that owns the registry behind the search palette
 * (`SearchDialog`), reachable as `app.search`. It lives in the kernel rather
 * than in a plugin so the palette works no matter which plugins are enabled:
 * plugins and modules only *contribute* groups (usually from the plugin's
 * `ready()` hook, once the stores they read from exist), and the dialog
 * renders whatever is registered, in registration order.
 *
 * @example
 * app.search.addGroup({
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
export class SearchExtension implements HawkiAppExtension {
    private _groups = $state<SearchGroup[]>([]);

    /** All registered groups, in registration order. Reactive. */
    public get groups(): readonly SearchGroup[] {
        return this._groups;
    }

    /** Whether a group with the given id is registered. */
    public hasGroup(id: string): boolean {
        return this._groups.some(group => group.id === id);
    }

    /** Registers a group; throws if its id is already taken. */
    public addGroup(group: SearchGroup): void {
        if (this.hasGroup(group.id)) {
            throw new Error(`Search group "${group.id}" is already registered.`);
        }
        this._groups = [...this._groups, group];
    }

    /** Removes the group with the given id; a no-op if none is registered. */
    public removeGroup(id: string): void {
        this._groups = this._groups.filter(group => group.id !== id);
    }

    /** Exposes this extension as `app.search`. */
    public provideProperties(): Record<string, any> {
        const extension = this;
        return {
            get search() {
                return extension;
            }
        };
    }
}

/**
 * An Orama index over the resolved rows of every registered group, plus
 * the rows themselves in contributor order. Built by `buildSearchIndex` and
 * consumed by `matchSearchGroups`; keep the two steps apart so the (cheap,
 * but not free) indexing only reruns when the *items* change, not on every
 * keystroke.
 */
export interface SearchIndex {
    groups: SearchGroupResult[];
    engine: SearchEngine;
}

const searchSchema = {
    title: 'string',
    keywords: 'string'
} as const;

type SearchEngine = Orama<typeof searchSchema>;

/**
 * Orama's `insert` and `search` are typed as "value or promise" because they
 * turn asynchronous once async hooks or plugins are registered. This index
 * registers none, so both resolve synchronously — which the palette relies on,
 * as it indexes and queries inside `$derived`. Fails loudly should that ever
 * change instead of silently handing a promise to the UI.
 */
function sync<T>(value: T | Promise<T>): T {
    if (value instanceof Promise) {
        throw new Error('The search index must stay synchronous; do not register async Orama hooks or plugins.');
    }
    return value;
}

/**
 * Resolves every group's live getters and indexes the rows with Orama
 * (fields: title and keywords). Because items only exist once their getter
 * runs, this is also where identity is validated: an id that already appeared
 * (in this or an earlier group) is reported and the later row skipped, so the
 * palette never renders two rows with one identity. Groups without rows are
 * dropped.
 */
export function buildSearchIndex(groups: readonly SearchGroup[]): SearchIndex {
    const seen = new Set<string>();
    const resolved: SearchGroupResult[] = [];
    const engine: SearchEngine = create({schema: searchSchema});

    for (const group of groups) {
        const items: SearchItem[] = [];
        for (const item of group.items()) {
            if (seen.has(item.id)) {
                console.error(`Search item id "${item.id}" in group "${group.id}" is not unique; row skipped.`);
                continue;
            }
            seen.add(item.id);
            items.push(item);
            sync(insert(engine, {id: item.id, title: item.title, keywords: item.keywords?.join(' ') ?? ''}));
        }
        if (items.length > 0) resolved.push({id: group.id, label: group.label(), items});
    }

    return {groups: resolved, engine};
}

/**
 * Narrows the indexed rows to `query`, ranked by Orama's relevance score:
 * every query term must match (as a prefix or within one edit of a term),
 * title hits outrank keyword hits. Rows keep their group; groups
 * are ordered by their best-scoring row and rows within a group by score, so
 * the most relevant hit sits at the top no matter which group contributed
 * it. An empty query returns every group and row in contributor order.
 */
export function matchSearchGroups(index: SearchIndex, query: string): SearchGroupResult[] {
    if (query.trim() === '') return index.groups;

    const hits = sync(
        search(index.engine, {
            term: query,
            properties: ['title', 'keywords'],
            tolerance: 1,
            threshold: 0,
            boost: {title: 2},
            limit: count(index.engine)
        })
    ).hits;
    const scores = new Map<string, number>();
    for (const hit of hits) scores.set(hit.id, hit.score);
    if (scores.size === 0) return [];

    const results: Array<SearchGroupResult & {best: number}> = [];
    for (const group of index.groups) {
        const items = group.items
            .filter(item => scores.has(item.id))
            .sort((a, b) => scores.get(b.id)! - scores.get(a.id)!);
        if (items.length === 0) continue;
        results.push({id: group.id, label: group.label, items, best: scores.get(items[0].id)!});
    }

    return results.sort((a, b) => b.best - a.best).map(({best: _best, ...group}) => group);
}
