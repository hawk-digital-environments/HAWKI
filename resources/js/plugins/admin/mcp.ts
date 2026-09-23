import type { AdminRow } from './schemas/admin-content.js';

/** Resolve an off-page selection once, using its stable ID even when labels are duplicated. */
export function revealServerId(selection: {
    selected: string | null;
    rows: readonly AdminRow[];
    loading: boolean;
    attempted: string | null;
}): string | null {
    const { selected, rows, loading, attempted } = selection;
    if (!selected || selected === attempted || loading) return null;
    return rows.some((row) => row.id === selected) ? null : selected;
}
