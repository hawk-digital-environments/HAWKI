/**
 * Ranking and publication: how scored candidates become the list a SearchBar
 * renders, and what may change once the user has frozen it.
 *
 * Relevance uses the inverted Fuse score. The immediate engine scores static
 * rows, a per-session engine scores what dynamic providers returned, and a remote row
 * the engine did not match stays eligible at score `0` in its provider's own
 * order. Ties fall back to registration order (provider first, then the
 * provider's own item order) so equally relevant rows never shuffle between two
 * publications.
 *
 * Freezing splits publication in two. Unfrozen, the whole list is re-derived
 * every time. Frozen, existing rows keep their slot and their snapshot: groups
 * that became ready afterwards are *appended*, nothing is inserted into a
 * visible group and nothing is ever evicted for a better match.
 */
import {
    SEARCH_ROWS_PER_GROUP,
    SEARCH_ROWS_TOTAL,
    type SearchGroupKind,
    type SearchGroupView,
    type SearchRow
} from '$lib/kernel/search/types.js';

/** A group's identity and heading, resolved for one publication pass. */
export interface RankingGroup {
    readonly id: string;
    readonly label: string;
    readonly kind: SearchGroupKind;
    /** Declaration order; the tiebreaker between equally relevant groups. */
    readonly order: number;
}

/** One row waiting to be ranked, with everything the comparators need. */
export interface RankingCandidate {
    readonly row: SearchRow;
    /** Inverted Fuse score, higher is better. `0` for a remote non-match and for a blank query. */
    readonly score: number;
    /** Higher is more recent. Only the blank-query recents path uses it. */
    readonly recency: number;
    /** The provider's declaration order. */
    readonly providerOrder: number;
    /** The row's position inside its provider's own result order. */
    readonly entryOrder: number;
    /** A live static copy wins a dedupe against a remote copy of the same entity. */
    readonly preferStatic: boolean;
}

/** How a frozen view is refreshed in place. */
export interface FrozenRefresh {
    /** Whether the entity behind a frozen row can still be selected. */
    isAvailable(row: SearchRow): boolean;
    /** Re-resolves a heading, e.g. after a locale change. */
    label(groupId: string): string;
}

/**
 * Collapses candidates that describe the same entity. A current static copy
 * always wins, so a conversation that also arrives from a server keeps its
 * local group and its local action; otherwise the better-ranked candidate wins.
 */
export function dedupeByEntity(candidates: readonly RankingCandidate[]): RankingCandidate[] {
    const best = new Map<string, RankingCandidate>();
    for (const candidate of candidates) {
        const current = best.get(candidate.row.entityKey);
        if (!current || outranks(candidate, current)) {
            best.set(candidate.row.entityKey, candidate);
        }
    }
    return [...best.values()];
}

/**
 * The full ranked view, **uncapped**: groups ordered by their best row, rows
 * ordered within their group. Capping is the caller's job because the frozen
 * and the unfrozen path spend the 20-row budget differently.
 */
export function rankGroups(
    candidates: readonly RankingCandidate[],
    groups: readonly RankingGroup[]
): SearchGroupView[] {
    const byGroup = new Map<string, RankingCandidate[]>();
    for (const candidate of dedupeByEntity(candidates)) {
        const bucket = byGroup.get(candidate.row.groupId);
        if (bucket) {
            bucket.push(candidate);
        } else {
            byGroup.set(candidate.row.groupId, [candidate]);
        }
    }

    const ranked: {group: RankingGroup; rows: RankingCandidate[]}[] = [];
    for (const group of groups) {
        const rows = byGroup.get(group.id);
        if (!rows || rows.length === 0) {
            continue;
        }
        rows.sort(byRelevance);
        ranked.push({group, rows});
    }

    ranked.sort((a, b) =>
        b.rows[0].score - a.rows[0].score
        || b.rows[0].recency - a.rows[0].recency
        || a.group.order - b.group.order);

    return ranked.map(({group, rows}) => ({
        id: group.id,
        label: group.label,
        kind: group.kind,
        items: rows.map(candidate => candidate.row)
    }));
}

/**
 * Five rows per group, 20 overall — the group cap first, so one very strong
 * group can never crowd out the rest. Lower-ranked groups may end up with fewer
 * rows or disappear entirely.
 */
export function applyRowLimits(groups: readonly SearchGroupView[]): SearchGroupView[] {
    return takeWithinBudget(groups, SEARCH_ROWS_TOTAL, () => true);
}

/**
 * The frozen counterpart: keeps `frozen` exactly as it is and appends only
 * groups that have never appeared in it, within whatever is left of the 20-row
 * budget. Never inserts into a visible group and never evicts a row.
 */
export function appendReadyGroups(
    frozen: readonly SearchGroupView[],
    ranked: readonly SearchGroupView[]
): SearchGroupView[] {
    const present = new Set(frozen.map(group => group.id));
    const entities = new Set(frozen.flatMap(group => group.items.map(row => row.entityKey)));
    const used = frozen.reduce((total, group) => total + group.items.length, 0);
    const newcomers = ranked.map(group => ({...group, items: group.items.filter(row => !entities.has(row.entityKey))}));
    return [...frozen, ...takeWithinBudget(newcomers, SEARCH_ROWS_TOTAL - used, group => !present.has(group.id))];
}

/**
 * Re-resolves headings and row availability without moving anything. A row
 * whose entity disappeared stays in its slot as a non-selectable placeholder so
 * the rows below it do not shift under the pointer.
 */
export function refreshFrozenGroups(
    groups: readonly SearchGroupView[],
    refresh: FrozenRefresh
): SearchGroupView[] {
    return groups.map(group => ({
        ...group,
        label: refresh.label(group.id),
        items: group.items.map(row => {
            const available = refresh.isAvailable(row);
            return available === row.available ? row : {...row, available};
        })
    }));
}

function takeWithinBudget(
    groups: readonly SearchGroupView[],
    budget: number,
    accept: (group: SearchGroupView) => boolean
): SearchGroupView[] {
    const result: SearchGroupView[] = [];
    let remaining = Math.max(0, budget);
    for (const group of groups) {
        if (remaining <= 0) {
            break;
        }
        if (!accept(group)) {
            continue;
        }
        const items = group.items.slice(0, Math.min(SEARCH_ROWS_PER_GROUP, remaining));
        if (items.length === 0) {
            continue;
        }
        remaining -= items.length;
        result.push({...group, items});
    }
    return result;
}

function byRelevance(a: RankingCandidate, b: RankingCandidate): number {
    return b.score - a.score
        || b.recency - a.recency
        || a.providerOrder - b.providerOrder
        || a.entryOrder - b.entryOrder;
}

function outranks(candidate: RankingCandidate, current: RankingCandidate): boolean {
    if (candidate.preferStatic !== current.preferStatic) {
        return candidate.preferStatic;
    }
    return byRelevance(candidate, current) < 0;
}
