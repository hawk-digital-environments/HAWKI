/**
 * Replaces the contents of `target` with the merge of `sources`, **mutating
 * `target` in place** instead of returning a new object.
 *
 * WHY this exists: Svelte 5 `$state` proxies notify on *property writes*, so
 * every holder of a reference stays live as long as that reference keeps its
 * identity. Reassigning the state variable to a freshly fetched object instead
 * (`this.current = await fetch(...)`) leaves everyone who already read it —
 * `const config = useConfig()`, `const connection = useConnection()` — holding
 * a detached snapshot that never updates again. Refreshing through this
 * function keeps the identity, so those plain `const`s keep working and no
 * hook has to hand out a `{get current()}` box just to defer the read.
 *
 * `sources` are merged left to right (later values win), then applied to
 * `target` such that `target` ends up structurally equal to that merge: keys
 * missing from the merged result are deleted from `target`, not left behind.
 * That matters for discriminated unions — an `internal_authenticated`
 * connection dropping back to `internal` must lose its `userinfo`, rather than
 * keeping a stale copy of it.
 *
 * The merge is shallow: nested objects are replaced wholesale rather than
 * updated in place, so a captured `const info = config.userinfo` does not stay
 * live across a refresh — read `config.userinfo.name` through the parent
 * reference instead.
 *
 * @param target The object to update in place. Its identity is preserved.
 * @param sources Objects to merge into `target`, left to right. Passing none
 *                clears `target`.
 *
 * @example
 * const connection = useConnection();
 * updateObject(connection, await fetchConnection(), {locale: 'de_DE'});
 * // `connection` is the same object, with fresh contents.
 */
export function updateObject<T extends object>(target: T, ...sources: object[]): void {
    const merged: Record<string, unknown> = Object.assign({}, ...sources);

    for (const key of Object.keys(target)) {
        if (!(key in merged)) {
            delete (target as Record<string, unknown>)[key];
        }
    }

    Object.assign(target, merged);
}
