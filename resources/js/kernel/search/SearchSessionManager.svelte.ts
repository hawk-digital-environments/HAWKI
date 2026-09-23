/**
 * Sessions: one independent view of the search index per SearchBar.
 *
 * The manager owns everything sessions *share* — the app-wide dynamic
 * concurrency budget and the recent-selection history — and nothing else. Each
 * session owns its own query, scope, freeze state, worker reply and dynamic
 * queries, so opening a second bar neither reloads a source nor cancels the
 * first bar's work. Static loading and the shared index stay with the extension
 * that hosts this manager.
 *
 * A session publishes in three waves, deliberately unsynchronised:
 *
 * 1. **Immediate**, on every accepted input: Fuse scores the immediate index
 *    on the main thread, without a debounce.
 * 2. **Worker**, when a source opted into worker matching: the same scores, one
 *    round-trip later, merged into the same ranking.
 * 3. **Dynamic**, after a 250 ms debounce: each group is published whole once
 *    all of its providers have settled, failures included.
 *
 * Relevance uses the inverted Fuse score throughout. Remote rows are scored
 * against a per-session engine built from what the providers returned; a remote
 * row the engine does not match stays eligible at score `0`, in its provider's order.
 */
import type {DynamicSource, SearchGroupView, SearchProviderError, SearchRow, SearchScope, SearchSession, SearchSessionOptions, SearchSessionState} from '$lib/kernel/search/types.js';
import {SEARCH_DYNAMIC_CANDIDATE_LIMIT, SEARCH_DYNAMIC_CONCURRENCY, SEARCH_DYNAMIC_MIN_QUERY_LENGTH} from '$lib/kernel/search/types.js';
import type {SearchSessionHost} from '$lib/kernel/search/sessionHost.js';
import type {SearchProviderDefinition} from '$lib/kernel/search/searchRegistry.js';
import {documentKey, type IndexedDocument} from '$lib/kernel/search/sharedIndex.js';
import {resolveSearchScope, scopeMatches, type KnownSearchScope, type ResolvedSearchScope} from '$lib/kernel/search/scope.js';
import {SearchEngine} from '$lib/kernel/search/searchEngine.js';
import {normalizeSearchQuery, queryLength} from '$lib/kernel/search/query.js';
import {SearchRecentsStore} from '$lib/kernel/search/sessionHistory.js';
import {
    appendReadyGroups,
    applyRowLimits,
    rankGroups,
    refreshFrozenGroups,
    type RankingCandidate,
    type RankingGroup
} from '$lib/kernel/search/sessionPublication.js';
import {
    ConcurrencyGate,
    DynamicQueryRunner,
    type DynamicProviderOutcome,
    type DynamicProviderTask
} from '$lib/kernel/search/sessionScheduler.js';

/**
 * The provider id a worker failure is reported under. The worker is shared
 * infrastructure rather than a contributor, but it still needs one retryable
 * identity in {@link SearchSessionState.providerErrors}.
 */
export const SEARCH_WORKER_PROVIDER_ID = 'kernel.search-worker';

export class SearchSessionManager {
    private readonly host: SearchSessionHost;
    private readonly gate: ConcurrencyGate;
    private readonly sessions = new Set<SearchSessionImpl>();
    private readonly recents: SearchRecentsStore;
    private disposed = false;

    public constructor(host: SearchSessionHost) {
        this.host = host;
        this.gate = new ConcurrencyGate(SEARCH_DYNAMIC_CONCURRENCY);
        this.recents = new SearchRecentsStore(host.app.localStorage, host.identity);
    }

    public createSession(options: SearchSessionOptions = {}): SearchSession {
        if (this.disposed) {
            throw new Error('The search session manager is disposed; no further sessions can be created.');
        }
        const session = new SearchSessionImpl(this.host, this.gate, this.recents, options, disposed => this.sessions.delete(disposed), () => this.invalidate());
        this.sessions.add(session);
        return session;
    }

    /**
     * The index, the active provider set, the locale or the static errors
     * changed. Sessions re-derive their local rows and, when a worker corpus
     * moved, re-ask the worker — but their dynamic queries keep running: a
     * renamed conversation must not cost a round of server requests.
     */
    public invalidate(): void {
        for (const session of [...this.sessions]) {
            session.refreshLocal();
        }
    }

    /**
     * The user or the connection changed. Everything the previous identity
     * produced is dropped — queries, results and freeze — and the history is
     * re-read for whoever is signed in now.
     */
    public reset(): void {
        this.recents.useIdentity(this.host.identity);
        for (const session of [...this.sessions]) {
            session.resetSession();
        }
    }

    public dispose(): void {
        this.disposed = true;
        for (const session of [...this.sessions]) {
            session.dispose();
        }
        this.sessions.clear();
    }
}

class SearchSessionImpl implements SearchSession {
    private readonly host: SearchSessionHost;
    private readonly recents: SearchRecentsStore;
    private readonly allowedScope: SearchScope | undefined;
    private readonly runner: DynamicQueryRunner;
    private readonly forget: (session: SearchSessionImpl) => void;
    private readonly selectionChanged: () => void;
    private readonly stateView: SearchSessionState;

    private published = $state<SearchGroupView[]>([]);
    private frozen = $state(false);
    private localPending = $state(false);
    private remotePending = $state(false);
    private dynamicErrors = $state<SearchProviderError[]>([]);
    private workerError = $state<string | null>(null);

    private query = '';
    private requestedScope: SearchScope | undefined;
    private resolvedScope: ResolvedSearchScope | null = null;
    private workerGeneration = 0;
    private workerRequestedRevision = -1;
    private workerReply: {query: string; revision: number; scores: Map<string, number>} | null = null;
    private workerController: AbortController | null = null;
    private readonly dynamicOutcomes = new Map<string, readonly DynamicProviderOutcome[]>();
    private dynamicCache: RankingCandidate[] | null = null;
    private disposed = false;
    private suspended = false;
    private dynamicProviders = new Map<string, AbortSignal>();

    public constructor(
        host: SearchSessionHost,
        gate: ConcurrencyGate,
        recents: SearchRecentsStore,
        options: SearchSessionOptions,
        forget: (session: SearchSessionImpl) => void,
        selectionChanged: () => void
    ) {
        this.host = host;
        this.recents = recents;
        this.allowedScope = options.allowedScope ? {...options.allowedScope} : undefined;
        this.forget = forget;
        this.selectionChanged = selectionChanged;
        this.runner = new DynamicQueryRunner(gate, {
            onGroupSettled: (groupId, outcomes) => this.acceptGroup(groupId, outcomes),
            onPendingChange: pending => {
                this.remotePending = pending;
            }
        });

        const session = this;
        this.stateView = {
            get groups() {
                return session.published;
            },
            get localPending() {
                return session.localPending;
            },
            get remotePending() {
                return session.remotePending;
            },
            get providerErrors() {
                return session.collectErrors();
            },
            get frozen() {
                return session.frozen;
            }
        };

        this.resolveScope();
        this.publish();
    }

    public get state(): SearchSessionState {
        return this.stateView;
    }

    public setInput(input: {query: string; scope?: SearchScope}): void {
        if (this.disposed) {
            return;
        }
        const query = normalizeSearchQuery(input?.query ?? '');
        const scope = input?.scope;
        // An unchanged input is not an input change: it must not clear the
        // freeze or cancel the queries that are already answering it.
        if (query === this.query && sameScope(scope, this.requestedScope)) {
            return;
        }
        this.query = query;
        this.requestedScope = scope ? {pluginId: scope.pluginId, moduleId: scope.moduleId} : undefined;
        this.restart();
    }

    public freezeOrder(): void {
        if (!this.disposed && this.published.some(group => group.items.some(row => row.available))) {
            this.frozen = true;
        }
    }

    public select(entityKey: string): SearchRow | null {
        if (this.disposed) {
            return null;
        }
        const active = this.activeProviderIds();
        for (const group of this.published) {
            for (const row of group.items) {
                if (row.entityKey !== entityKey) {
                    continue;
                }
                const entry = this.liveEntry(row, active);
                if (!row.available || !entry) {
                    return null;
                }
                // Only local entities are worth remembering: a remote hit has no
                // stable local row to resolve the id back to on the next visit.
                if (row.kind === 'static') {
                    this.recents.record(entityKey);
                    this.selectionChanged();
                }
                return {...entry, providerId: row.providerId, groupId: row.groupId, kind: row.kind, available: true};
            }
        }
        return null;
    }

    public retry(providerId: string): void {
        if (this.disposed) {
            return;
        }
        if (providerId === SEARCH_WORKER_PROVIDER_ID) {
            if (this.workerError === null) return;
            this.host.retryWorker();
            this.startWorker(true);
            return;
        }

        const definition = this.host.registry.provider(providerId);
        if (!definition) {
            return;
        }
        if (definition.kind === 'static') {
            this.host.retryStatic(providerId);
            return;
        }

        const active = this.host.providers.find(provider => provider.definition.id === providerId);
        if (!active || active.signal.aborted || !this.acceptsDynamicQueries()) {
            return;
        }
        if (!this.dynamicErrors.some(error => error.providerId === providerId)) return;
        this.dynamicErrors = this.dynamicErrors.filter(error => error.providerId !== providerId);
        // The group's successful siblings stay visible while the retry runs; the
        // runner reports the whole group again once it settles.
        // Deliberately does not touch `frozen`: retrying is a repair, not a new input.
        this.runner.retry(this.taskFor(active.definition, active.signal));
    }

    public suspend(suspended: boolean = true): void {
        if (this.disposed || this.suspended === suspended) return;
        this.suspended = suspended;
        this.runner.suspend(suspended);
        if (suspended) {
            this.cancelWorker();
            this.runner.start(this.dynamicTasks());
        } else this.startWorker(false);
    }

    public dispose(): void {
        if (this.disposed) {
            return;
        }
        this.disposed = true;
        this.runner.dispose();
        this.cancelWorker();
        this.dynamicOutcomes.clear();
        this.dynamicCache = null;
        this.published = [];
        this.remotePending = false;
        this.dynamicErrors = [];
        this.workerError = null;
        this.forget(this);
    }

    /** @see SearchSessionManager.invalidate */
    public refreshLocal(): void {
        if (this.disposed) {
            return;
        }
        this.resolveScope();
        this.syncDynamicProviders();
        this.dynamicCache = null;
        if (this.host.index.hasWorkerDocuments && this.host.index.revision !== this.workerRequestedRevision) {
            this.startWorker(false);
        }
        if (!this.host.index.hasWorkerDocuments) {
            this.cancelWorker();
            this.workerReply = null;
            this.workerError = null;
        }
        this.publish();
    }

    /** @see SearchSessionManager.reset */
    public resetSession(): void {
        if (this.disposed) {
            return;
        }
        this.runner.cancel();
        this.cancelWorker();
        this.query = '';
        this.requestedScope = undefined;
        this.frozen = false;
        this.dynamicErrors = [];
        this.workerError = null;
        this.dynamicOutcomes.clear();
        this.dynamicCache = null;
        this.dynamicProviders.clear();
        this.published = [];
        this.resolveScope();
        this.publish();
    }

    private restart(): void {
        this.frozen = false;
        this.resolveScope();
        this.cancelWorker();
        this.workerError = null;
        this.dynamicErrors = [];
        this.dynamicOutcomes.clear();
        this.dynamicCache = null;
        this.syncDynamicProviders(true);
        this.startWorker(false);
        this.publish();
    }

    private acceptGroup(groupId: string, outcomes: readonly DynamicProviderOutcome[]): void {
        if (this.disposed) {
            return;
        }
        this.dynamicOutcomes.set(groupId, outcomes);
        this.dynamicCache = null;
        this.dynamicErrors = [
            ...this.dynamicErrors.filter(error => error.groupId !== groupId),
            ...outcomes
                .filter(outcome => outcome.status === 'failed')
                .map(outcome => ({providerId: outcome.providerId, groupId, message: outcome.message ?? 'The search request failed.'}))
        ];
        this.publish();
    }

    private publish(): void {
        const active = this.activeProviderIds();
        const ranked = this.rank(active);
        if (!this.frozen) {
            this.published = applyRowLimits(ranked);
            return;
        }
        const kept = refreshFrozenGroups(this.published, {
            isAvailable: row => this.isRowAvailable(row, active),
            label: groupId => this.host.groupLabel(groupId)
        });
        this.published = appendReadyGroups(kept, ranked);
    }

    private rank(active: ReadonlySet<string>): SearchGroupView[] {
        const scope = this.resolvedScope;
        if (scope === null) {
            return [];
        }
        const local = this.staticCandidates(scope, active);
        const remote = this.dynamicCandidates(active).map(candidate => {
            const copy = this.host.index.documentsForEntity(candidate.row.entityKey, scope)
                .find(document => active.has(document.provider.id));
            return copy ? staticCandidate(copy, candidate.score, 0) : candidate;
        });
        const candidates = [...local, ...remote];
        return candidates.length === 0 ? [] : rankGroups(candidates, this.rankingGroups(candidates));
    }

    private staticCandidates(scope: ResolvedSearchScope, active: ReadonlySet<string>): RankingCandidate[] {
        if (this.query === '') {
            return this.recentCandidates(scope, active);
        }

        const candidates: RankingCandidate[] = [];
        const immediate = new Map(this.host.index.searchScores(this.query));
        for (const document of this.host.index.candidates(scope, 'immediate')) {
            const score = immediate.get(document.key);
            if (score !== undefined && active.has(document.provider.id)) {
                candidates.push(staticCandidate(document, score, 0));
            }
        }

        const worker = this.workerScores();
        if (worker !== null) {
            for (const document of this.host.index.candidates(scope, 'worker')) {
                const score = worker.get(document.key);
                if (score !== undefined && active.has(document.provider.id)) {
                    candidates.push(staticCandidate(document, score, 0));
                }
            }
        }
        return candidates;
    }

    /**
     * A blank query is answered from the recent selections that still resolve to
     * a live in-scope row, most recent first. Only when none of them do does the
     * list fall back to the sources' own order — unused recent slots are never
     * padded with unrelated entries.
     */
    private recentCandidates(scope: ResolvedSearchScope, active: ReadonlySet<string>): RankingCandidate[] {
        const candidates: RankingCandidate[] = [];
        const selections = this.recents.entries;
        for (let index = 0; index < selections.length; index++) {
            const recency = selections.length - index;
            for (const document of this.host.index.documentsForEntity(selections[index].entityKey, scope)) {
                if (document.provider.kind !== 'static' || !active.has(document.provider.id)) {
                    continue;
                }
                candidates.push(staticCandidate(document, 0, recency));
                break;
            }
        }
        if (candidates.length > 0) {
            return candidates;
        }

        return this.host.index.candidates(scope)
            .filter(document => document.provider.kind === 'static' && active.has(document.provider.id))
            .map(document => staticCandidate(document, 0, 0));
    }

    /**
     * Scores what the dynamic providers returned with a per-session Fuse
     * engine, so a remote row is ranked by the same measure as a local one. A
     * row the engine misses keeps score `0` and therefore the server's order.
     */
    private dynamicCandidates(active: ReadonlySet<string>): RankingCandidate[] {
        if (this.dynamicCache !== null) {
            return this.dynamicCache;
        }

        const engine = new SearchEngine();
        const pending: {row: SearchRow; key: string; providerOrder: number; entryOrder: number}[] = [];
        for (const outcomes of this.dynamicOutcomes.values()) {
            for (const outcome of outcomes) {
                const definition = this.host.registry.provider(outcome.providerId);
                if (outcome.status !== 'fulfilled' || !definition || !active.has(outcome.providerId)) {
                    continue;
                }
                const seen = new Set<string>();
                let entryOrder = 0;
                for (const entry of outcome.entries) {
                    if (!isUsableEntry(entry) || seen.has(entry.id)) {
                        continue;
                    }
                    seen.add(entry.id);
                    const key = documentKey(outcome.providerId, entry.id);
                    engine.upsert({
                        id: key,
                        title: entry.title ?? '',
                        keywords: (entry.keywords ?? []).join(' '),
                        content: entry.content ?? ''
                    });
                    pending.push({
                        row: {
                            ...entry,
                            providerId: outcome.providerId,
                            groupId: definition.groupId,
                            kind: 'dynamic',
                            available: true
                        },
                        key,
                        providerOrder: definition.order,
                        entryOrder: entryOrder++
                    });
                }
            }
        }

        const scores = new Map(engine.search(this.query));
        this.dynamicCache = pending.map(item => ({
            row: item.row,
            score: scores.get(item.key) ?? 0,
            recency: 0,
            providerOrder: item.providerOrder,
            entryOrder: item.entryOrder,
            preferStatic: false
        }));
        return this.dynamicCache;
    }

    private rankingGroups(candidates: readonly RankingCandidate[]): RankingGroup[] {
        const groups = new Map<string, RankingGroup>();
        for (const candidate of candidates) {
            if (groups.has(candidate.row.groupId)) {
                continue;
            }
            const definition = this.host.registry.group(candidate.row.groupId);
            if (definition) {
                groups.set(definition.id, {
                    id: definition.id,
                    label: this.host.groupLabel(definition.id),
                    kind: definition.kind,
                    order: definition.order
                });
            }
        }
        return [...groups.values()];
    }

    private dynamicTasks(): DynamicProviderTask[] {
        const scope = this.resolvedScope;
        if (scope === null || !this.acceptsDynamicQueries()) {
            return [];
        }
        const tasks: DynamicProviderTask[] = [];
        for (const {definition, signal} of this.host.providers) {
            if (definition.kind === 'dynamic' && !signal.aborted && scopeMatches(scope, definition)) {
                tasks.push(this.taskFor(definition, signal));
            }
        }
        return tasks;
    }

    private syncDynamicProviders(force = false): void {
        const next = new Map(this.host.providers
            .filter(({definition, signal}) => definition.kind === 'dynamic' && !signal.aborted
                && this.resolvedScope !== null && scopeMatches(this.resolvedScope, definition))
            .map(({definition, signal}) => [definition.id, signal] as const));
        if (!force && next.size === this.dynamicProviders.size
            && [...next].every(([id, signal]) => this.dynamicProviders.get(id) === signal)) return;
        this.dynamicProviders = next;
        this.dynamicOutcomes.clear();
        this.dynamicErrors = [];
        this.dynamicCache = null;
        this.runner.start(this.dynamicTasks());
    }

    private taskFor(definition: SearchProviderDefinition, providerSignal: AbortSignal): DynamicProviderTask {
        // The query is captured now: the task must answer the input it was built
        // for, even if the session has moved on by the time it is dispatched.
        const query = this.query;
        const source = definition.source as DynamicSource;
        return {
            providerId: definition.id,
            groupId: definition.groupId,
            search: signal => source.search({
                app: this.host.app,
                signal: AbortSignal.any([signal, providerSignal]),
                query,
                limit: SEARCH_DYNAMIC_CANDIDATE_LIMIT
            })
        };
    }

    private startWorker(force: boolean): void {
        this.cancelWorker();
        if (this.suspended || !this.host.index.hasWorkerDocuments || this.query === '' || this.resolvedScope === null
            || this.host.index.candidates(this.resolvedScope, 'worker').length === 0) {
            this.localPending = false;
            return;
        }
        if (!force && this.workerReply !== null && this.workerReply.query === this.query
            && this.workerReply.revision === this.host.index.revision) {
            return;
        }

        const generation = ++this.workerGeneration;
        const query = this.query;
        const controller = new AbortController();
        this.workerController = controller;
        this.workerRequestedRevision = this.host.index.revision;
        this.localPending = true;

        void this.host.queryWorker(query, controller.signal).then(
            reply => this.acceptWorkerReply(generation, query, reply),
            error => this.rejectWorkerReply(generation, error)
        );
    }

    private acceptWorkerReply(generation: number, query: string, reply: {revision: number; scores: Array<[string, number]>}): void {
        // Three ways a reply can be worthless: the session moved on, the query
        // moved on, or the corpus it was computed against is older than one we
        // already accepted.
        if (this.disposed || generation !== this.workerGeneration || query !== this.query) {
            return;
        }
        if (reply.revision !== this.host.index.revision) {
            this.startWorker(true);
            return;
        }
        if (this.workerReply !== null && this.workerReply.query === query && reply.revision < this.workerReply.revision) {
            return;
        }
        this.workerController = null;
        this.workerReply = {query, revision: reply.revision, scores: new Map(reply.scores)};
        this.workerError = null;
        this.localPending = false;
        this.publish();
    }

    private rejectWorkerReply(generation: number, error: unknown): void {
        if (this.disposed || generation !== this.workerGeneration) {
            return;
        }
        this.workerController = null;
        this.localPending = false;
        // Immediate and dynamic groups stay usable; only the worker corpus is missing.
        this.workerError = error instanceof Error && error.message !== ''
            ? error.message
            : 'Local search matching is unavailable.';
    }

    private workerScores(): Map<string, number> | null {
        return this.workerReply !== null && this.workerReply.query === this.query ? this.workerReply.scores : null;
    }

    private cancelWorker(): void {
        this.workerController?.abort();
        this.workerController = null;
        this.workerGeneration++;
        this.localPending = false;
    }

    private collectErrors(): SearchProviderError[] {
        const scope = this.resolvedScope;
        const hostErrors = scope === null
            ? []
            : this.host.errors.filter(error => {
                const definition = this.host.registry.provider(error.providerId);
                return definition !== null && scopeMatches(scope, definition);
            });
        const worker = this.workerError === null
            ? []
            : [{providerId: SEARCH_WORKER_PROVIDER_ID, groupId: '', message: this.workerError}];
        return [...hostErrors, ...this.dynamicErrors, ...worker];
    }

    private isRowAvailable(row: SearchRow, active: ReadonlySet<string>): boolean {
        return this.liveEntry(row, active) !== null;
    }

    private liveEntry(row: SearchRow, active: ReadonlySet<string>) {
        if (!active.has(row.providerId) || this.resolvedScope === null) return null;
        const provider = this.host.registry.provider(row.providerId);
        if (!provider || !scopeMatches(this.resolvedScope, provider)) return null;
        if (row.kind === 'static') {
            const document = this.host.index.document(documentKey(row.providerId, row.id));
            return document?.entry.entityKey === row.entityKey ? document.entry : null;
        }
        const outcome = this.dynamicOutcomes.get(row.groupId)?.find(outcome => outcome.providerId === row.providerId);
        return outcome?.entries.find(entry => entry.id === row.id && entry.entityKey === row.entityKey) ?? null;
    }

    /** Whether the current input is even eligible for server queries. */
    private acceptsDynamicQueries(): boolean {
        return this.resolvedScope !== null && queryLength(this.query) >= SEARCH_DYNAMIC_MIN_QUERY_LENGTH;
    }

    private activeProviderIds(): ReadonlySet<string> {
        const ids = new Set<string>();
        for (const {definition, signal} of this.host.providers) {
            if (!signal.aborted) {
                ids.add(definition.id);
            }
        }
        return ids;
    }

    private resolveScope(): void {
        const known: KnownSearchScope = {modules: new Map(this.host.providers.map(({definition}) => [definition.moduleId, definition.pluginId]))};
        const resolution = resolveSearchScope(this.allowedScope, this.requestedScope, known);
        this.resolvedScope = resolution.valid ? resolution.scope : null;
    }
}

function staticCandidate(document: IndexedDocument, score: number, recency: number): RankingCandidate {
    return {
        row: {
            ...document.entry,
            providerId: document.provider.id,
            groupId: document.provider.groupId,
            kind: document.provider.kind,
            available: true
        },
        score,
        recency,
        providerOrder: document.provider.order,
        entryOrder: document.entryOrder,
        preferStatic: true
    };
}

function isUsableEntry(entry: {id?: unknown; entityKey?: unknown; onSelect?: unknown} | null | undefined): boolean {
    return !!entry
        && typeof entry.id === 'string' && entry.id !== ''
        && typeof entry.entityKey === 'string' && entry.entityKey !== ''
        && typeof entry.onSelect === 'function';
}

function sameScope(a: SearchScope | undefined, b: SearchScope | undefined): boolean {
    return (a?.pluginId ?? null) === (b?.pluginId ?? null) && (a?.moduleId ?? null) === (b?.moduleId ?? null);
}
