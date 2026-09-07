/**
 * Query normalization shared by every search session.
 *
 * One canonical form for the whole pipeline: the immediate engine, the worker
 * and every dynamic provider see the exact same string, so a session can
 * compare two inputs for equality and skip work that would produce identical
 * results.
 */

/**
 * Canonical form of a raw input: compatibility-composed (so full-width and
 * decomposed input match their plain counterparts), trimmed, inner whitespace
 * collapsed and lower-cased.
 *
 * Lower-casing is locale-independent on purpose — matching must not change with
 * the UI language, or the same query would rank differently for two users
 * looking at the same data.
 */
export function normalizeSearchQuery(value: string): string {
    return String(value ?? '').normalize('NFKC').trim().replace(/\s+/gu, ' ').toLowerCase();
}

/**
 * Length in codepoints, so an emoji or any other surrogate pair counts as the
 * one character the user typed. This is what the dynamic minimum length is
 * measured in.
 */
export function queryLength(query: string): number {
    return [...query].length;
}
