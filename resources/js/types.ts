/**
 * Shared root-level TypeScript helper types for the frontend.
 *
 * This file is the place for small, generic, framework-agnostic type
 * utilities that are used across kernel/, app/, and plugins/ code — as
 * opposed to `kernel/extendableTypes.ts`, which holds the app's *extendable*
 * (declaration-merged) interfaces. Keep this file free of anything
 * app-specific; only add truly generic type helpers here.
 */

/**
 * The subset of `keyof T` that are actual `string` keys (excludes `number`
 * and `symbol` keys, and excludes `keyof T` results that TypeScript widens to
 * `string | number` for index-signature types).
 *
 * Use this whenever you need to iterate over or type an object's keys as
 * plain strings, e.g. `Object.keys(obj) as RecordKeys<typeof obj>[]`.
 */
export type RecordKeys<T> = keyof T & string;
