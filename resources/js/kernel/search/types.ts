/**
 * Public contract of the all-app search provider.
 *
 * Everything a plugin/module developer or a SearchBar author touches lives
 * here; the implementation modules beside this file are internal. Three roles
 * meet in this file:
 *
 * - **Contributors** declare {@link SearchGroup}s and register a
 *   {@link StaticSource} or {@link DynamicSource} per group from
 *   `HawkiModule.search()` (see `kernel/modules/types.ts`). They supply data
 *   and selection actions; the kernel owns subscriptions, indexing, ranking
 *   and display limits.
 * - **Consumers** (SearchBar and friends) call
 *   `app.search.createSession()` and read {@link SearchSessionState}.
 * - **The kernel** wires the two together via the shared static indexes and
 *   the app-wide dynamic query scheduler.
 *
 * @see `_plans/all-app-search-provider.html` for the design this implements.
 */
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';
import type {IconComponent} from '$lib/components/ui/icons/index.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {Readable} from 'svelte/store';

/**
 * Whether a group is fed from locally held data (`static`) or from a server
 * query per keystroke (`dynamic`). A group has exactly one kind and may hold
 * several providers *of that kind*; never mix them (see
 * {@link ModuleSearchRegistrar}).
 *
 * "Static" says *where* the data lives, not that it never changes: a source
 * may load once and keep changing afterwards. Downloading chat messages in
 * the background populates a static source; it does not make it dynamic.
 */
export type SearchGroupKind = 'static' | 'dynamic';

/**
 * What a source callback receives. `app` is the fully assembled application —
 * callbacks only run after the store-loading stage, so `app.stores.get(...)`
 * is safe. `signal` aborts when the source is deactivated (disabled, module
 * removed, identity change) or, for a dynamic query, when the query is
 * superseded, cancelled or hits its deadline.
 */
export interface SearchRuntime {
    readonly app: HawkiApp;
    readonly signal: AbortSignal;
}

/** A dynamic provider's query, on top of the shared {@link SearchRuntime}. */
export interface DynamicSearchRuntime extends SearchRuntime {
    /** The normalized query. Never blank — blank input never reaches a provider. */
    readonly query: string;
    /**
     * How many candidates the kernel would like. This is a *request budget*,
     * not palette capacity: the kernel still ranks and caps what it shows.
     */
    readonly limit: number;
}

/**
 * One searchable thing contributed by a provider.
 *
 * `id` only has to be unique inside its own provider — the kernel namespaces
 * it into a document key. `entityKey` is the cross-provider identity of the
 * *thing* behind the row and is what deduplication, selection and the recents
 * history use, so two providers describing the same conversation must agree on
 * it (e.g. `ai-convs/<slug>`).
 *
 * `title`, `keywords` and `content` are matched; `description` is display
 * only. `onSelect` runs after the palette has closed itself.
 */
export interface SearchEntry {
    /** Unique within this provider. */
    id: string;
    /** Stable identity across providers, e.g. `ai-convs/<slug>`. */
    entityKey: string;
    title: string;
    /** Display only; never matched. */
    description?: string;
    icon?: IconComponent;
    /** Extra terms the entry is findable by. Never shown. */
    keywords?: readonly string[];
    /** Optional searchable text or excerpt. */
    content?: string;
    onSelect(): void;
}

/**
 * A provider backed by locally held data.
 *
 * `items` is a synchronous reactive getter, or returns a Svelte readable
 * store. It never receives the query — the kernel indexes what it returns and
 * does the matching. A plain, non-reactive array is a fixed snapshot; wrap the
 * data in `$state` or emit from a store if it must change.
 *
 * The optional `load` runs **once per activation**, shared by every SearchBar,
 * and fills the source's own reactive data. The kernel observes/subscribes
 * *before* starting the loader, so an early emission is never missed.
 *
 * @example
 * const chatTitleSource: StaticSource = {
 *     items: ({app}) => app.stores.get('chat').conversations.map(c => ({
 *         id: c.slug,
 *         entityKey: `ai-convs/${c.slug}`,
 *         title: c.name,
 *         onSelect: () => void app.router.goToRoute('chat.conversation', {slug: c.slug})
 *     }))
 * };
 */
export interface StaticSource {
    /**
     * Where matching runs. `immediate` (the default) matches on the main
     * thread without debounce; `worker` moves matching into the shared search
     * worker, which suits a large corpus such as the chat message index at the
     * cost of arriving a tick later.
     */
    matchIn?: 'immediate' | 'worker';
    /** Reactive. Disabling aborts the source's work, drops its entries and disposes its subscriptions. */
    enabled?(context: SearchRuntime): boolean;
    items(context: SearchRuntime): readonly SearchEntry[] | Readable<readonly SearchEntry[]>;
    /** Runs once per activation. Must honor `signal` and must not overwrite newer module data. */
    load?(context: SearchRuntime): void | Promise<void>;
}

/**
 * A provider that answers each query from its own API.
 *
 * The kernel debounces dynamic queries, bounds their concurrency and enforces
 * a deadline; the provider only has to map its response to
 * {@link SearchEntry}s and return them in the server's preferred order. No
 * match class or rank is expected — the kernel ranks.
 */
export interface DynamicSource {
    /** Reactive. A disabled provider is never queried. */
    enabled?(context: SearchRuntime): boolean;
    search(context: DynamicSearchRuntime): Promise<readonly SearchEntry[]>;
}

/** Anything a group can be declared with. `label` is re-evaluated on locale change. */
export interface SearchGroupOptions {
    label(translate: Translator['translate']): string;
}

/**
 * Adds sources to one already-declared group. `add` returns the same
 * registrar so several providers can be chained onto one group.
 */
export interface SearchGroupRegistrar<TSource> {
    add(name: string, source: TSource): SearchGroupRegistrar<TSource>;
}

/**
 * What `HawkiModule.search()` receives.
 *
 * The overloads tie a group's `kind` to the source type its registrar
 * accepts, so a dynamic source cannot end up in a static group at compile
 * time; the same rule is enforced at runtime. Ownership (plugin and module)
 * comes from the registration context — a module can never declare groups for
 * another module.
 *
 * Names are module-local and are namespaced by the kernel: module `chat` of
 * plugin `core` declaring group `messages` yields the group id
 * `core:chat.messages`. Group names and provider names live in **separate**
 * registries, so a group and a provider may share a name.
 *
 * @example
 * search({group}: ModuleSearchRegistrar): void {
 *     group('messages', {kind: 'static', label: t => t('ui.search.messages')})
 *         .add('messages', chatMessageSource);
 * }
 */
export interface ModuleSearchRegistrar {
    group(name: string, options: SearchGroupOptions & {kind: 'static'}): SearchGroupRegistrar<StaticSource>;
    group(name: string, options: SearchGroupOptions & {kind: 'dynamic'}): SearchGroupRegistrar<DynamicSource>;
}

/**
 * Narrows a session to one plugin or module. Both filters combine with AND
 * and picking a module implies its plugin. A session's `allowedScope` is the
 * outer bound; user-chosen scope may only narrow it further. Unknown ids or a
 * scope that conflicts with the allowed one yield no rows and no requests.
 */
export interface SearchScope {
    pluginId?: string;
    moduleId?: string;
}

/** The filter choices a SearchBar can offer, already labelled and in registration order. */
export interface SearchScopeOptions {
    plugins: readonly {id: string; label: string}[];
    modules: readonly {id: string; pluginId: string; label: string}[];
}

/**
 * One rendered row: the contributor's entry plus who produced it and whether
 * it can still be selected.
 *
 * `available` turns `false` when the entity behind a *visible* row disappears
 * (deleted, provider disabled, access lost). While the order is frozen the
 * row stays in place as a non-selectable placeholder so nothing below it
 * moves; {@link SearchSession.select} refuses it.
 */
export interface SearchRow extends SearchEntry {
    providerId: string;
    groupId: string;
    kind: SearchGroupKind;
    available: boolean;
}

/** A group as published to the UI: heading plus its capped, ranked rows. */
export interface SearchGroupView {
    id: string;
    label: string;
    kind: SearchGroupKind;
    items: readonly SearchRow[];
}

/** A provider that failed to load or query, with the message to show beside its retry affordance. */
export interface SearchProviderError {
    providerId: string;
    groupId: string;
    message: string;
}

/**
 * Everything a SearchBar renders. Reactive: read it inside `$derived`/markup
 * and it updates as groups arrive, as the index changes and as the freeze
 * state flips.
 */
export interface SearchSessionState {
    groups: readonly SearchGroupView[];
    /** Explicit worker matching is running. */
    localPending: boolean;
    /** Dynamic query work is scheduled or in flight. */
    remotePending: boolean;
    providerErrors: readonly SearchProviderError[];
    frozen: boolean;
}

/**
 * One SearchBar's independent view of the search index.
 *
 * A session owns query, scope, freeze state and its own dynamic queries.
 * Static loading and indexes are shared, so opening a second bar neither
 * reloads sources nor cancels the first bar's work.
 */
export interface SearchSession {
    readonly state: SearchSessionState;
    /** Applying a *changed* input resumes ranking (clears the freeze) and cancels superseded queries. */
    setInput(input: {query: string; scope?: SearchScope}): void;
    /** Call on real pointer movement into the rows, or on keyboard selection. */
    freezeOrder(): void;
    /**
     * Validates the row behind `entityKey`, records it in the static recents
     * history and returns it, or `null` when it is gone/unavailable. The
     * caller closes its host and then invokes `row.onSelect()`.
     */
    select(entityKey: string): SearchRow | null;
    /** Re-runs one failed provider for the current input. Preserves the freeze. */
    retry(providerId: string): void;
    /** Suspends dynamic dispatch, e.g. during IME composition. `suspend(false)` resumes and flushes. */
    suspend(suspended?: boolean): void;
    dispose(): void;
}

/** Options accepted by `app.search.createSession()`. */
export interface SearchSessionOptions {
    /** Hard outer bound on what this session may ever search. */
    allowedScope?: SearchScope;
}

/** Consumer-facing subset of app.search, also usable by embedded SearchBars. */
export interface SearchApi {
    readonly scopeOptions: SearchScopeOptions;
    createSession(options?: SearchSessionOptions): SearchSession;
}

/** At most this many rows per group. A single strong group can never crowd out the rest. */
export const SEARCH_ROWS_PER_GROUP = 5;

/** At most this many rows overall, headings and status messages excluded. */
export const SEARCH_ROWS_TOTAL = 20;

/** At most this many recent selections are remembered per user/connection. */
export const SEARCH_RECENTS_LIMIT = 10;

/** Dynamic providers are debounced by this much; static matching is never debounced. */
export const SEARCH_DYNAMIC_DEBOUNCE_MS = 250;

/** Shorter queries never reach a dynamic provider. */
export const SEARCH_DYNAMIC_MIN_QUERY_LENGTH = 2;

/** How many dynamic requests may be in flight app-wide, across every session. */
export const SEARCH_DYNAMIC_CONCURRENCY = 4;

/** A dynamic request that outlives this settles as a retryable failure. */
export const SEARCH_DYNAMIC_DEADLINE_MS = 5000;

/** Candidate budget handed to a dynamic provider as `limit`. */
export const SEARCH_DYNAMIC_CANDIDATE_LIMIT = 20;
