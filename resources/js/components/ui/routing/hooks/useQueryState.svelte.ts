import { untrack } from 'svelte';
import { useRouter } from '$lib/components/ui/routing/hooks/useRouter.svelte.js';
import type { RouterHandle } from '$lib/components/ui/routing/logistics/router.js';

interface QueryStateSettings<T> {
    /** Used when the parameter is absent or parsing returns null. Assigning it removes the parameter. */
    defaultValue?: T;
    /** Decode a parameter. Return null for invalid input to use the default. */
    parse?: (raw: string) => T | null;
    /** Encode a value. Defaults to String(value). */
    serialize?: (value: T) => string;
    /** Replace the current history entry by default; use push to make each change reachable with Back. */
    history?: 'push' | 'replace';
    /** Router name, as in useRouter(name). Defaults to the enclosing router. */
    router?: string;
}

/** Non-string state requires a parser so URL reads have the declared type. */
export type QueryStateOptions<T = string> = QueryStateSettings<T> &
    ([T] extends [string | null] ? {} : { parse: (raw: string) => T | null });

export interface QueryState<T> {
    /** Reactive value. Assign to this property to update the URL, including for object values. */
    current: T;
}

/**
 * Reactive state stored in one query parameter. Call during component initialization.
 * Reads follow URL navigation; assignments preserve other parameters and the fragment.
 * Query changes keep the page mounted and do not rerun route loaders.
 *
 * @example
 * const provider = useQueryState('provider_id');
 * // Use bind:value={provider.current}, or assign provider.current = 'openai'.
 * const page = useQueryState('page', {
 *     defaultValue: 1,
 *     parse: raw => /^\d+$/.test(raw) ? Number(raw) : null
 * });
 */
export function useQueryState(
    key: string,
    options?: Omit<QueryStateOptions<string>, 'defaultValue' | 'parse' | 'serialize'>
): QueryState<string | null>;
export function useQueryState<T>(key: string, options: QueryStateOptions<T> & { defaultValue: T }): QueryState<T>;
export function useQueryState<T>(key: string, options: QueryStateOptions<T>): QueryState<T | null>;
export function useQueryState<T>(key: string, options: QueryStateSettings<T> = {}): QueryState<T | null> {
    return bindQueryState(useRouter(options.router), key, options);
}

/** Query state for an existing router handle. Does not require component context; ignores options.router. */
export function createQueryState(
    router: RouterHandle,
    key: string,
    options?: Omit<QueryStateOptions<string>, 'defaultValue' | 'parse' | 'serialize' | 'router'>
): QueryState<string | null>;
export function createQueryState<T>(
    router: RouterHandle,
    key: string,
    options: QueryStateOptions<T> & { defaultValue: T }
): QueryState<T>;
export function createQueryState<T>(
    router: RouterHandle,
    key: string,
    options: QueryStateOptions<T>
): QueryState<T | null>;
export function createQueryState<T>(
    router: RouterHandle,
    key: string,
    options: QueryStateSettings<T> = {}
): QueryState<T | null> {
    return bindQueryState(router, key, options);
}

function bindQueryState<T>(router: RouterHandle, key: string, options: QueryStateSettings<T>): QueryState<T | null> {
    // The public overloads require a parser for non-string values.
    const parse = options.parse ?? ((raw: string) => raw as T);
    const serialize = options.serialize ?? ((value: T) => String(value));
    const fallback = options.defaultValue ?? null;
    // Compare encoded defaults so objects can match by value.
    const serializedDefault = fallback === null ? null : serialize(fallback);
    const current = $derived.by((): T | null => {
        const raw = router.query.get(key);
        return raw === null ? fallback : (parse(raw) ?? fallback);
    });

    return {
        get current(): T | null {
            return current;
        },
        set current(value: T | null) {
            // Assignment must not subscribe its caller to the URL it updates.
            untrack(() => {
                const raw = value === null || value === undefined ? null : serialize(value);
                router.setQuery(
                    { [key]: raw === serializedDefault ? null : raw },
                    { replace: options.history !== 'push' }
                );
            });
        }
    };
}
