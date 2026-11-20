import z from 'zod';

/**
 * How far an assistant has been released — the visibility ladder a draft climbs
 * on its way to being usable by other people.
 *
 * Kept as a TypeScript enum (not a plain union) because the builder references
 * the members as *values* (e.g. `ReleaseMode.PRIVATE`). {@link ReleaseModeSchema}
 * is the matching Zod validator; `z.enum()` accepts a native enum directly.
 */
export enum ReleaseMode {
    /** Not released at all: only the creator sees it, still being built. */
    DRAFT = 'draft',
    /** Usable by the creator only, but complete/saved rather than in-progress. */
    PRIVATE = 'private',
    /** Shared inside the creator's own organization. */
    ORGANIZATIONAL = 'organizational',
    /** Shared across all federated organizations. */
    FEDERATED = 'federated'
}

export const ReleaseModeSchema = z.enum(ReleaseMode);