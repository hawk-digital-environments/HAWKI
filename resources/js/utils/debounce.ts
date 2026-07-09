/**
 * Returns a debounced version of `func` that delays invoking it until `wait`
 * milliseconds have elapsed since the last call. Repeated calls within the
 * window reset the timer, so the original function is called only once the
 * caller goes quiet.
 *
 * The returned function has the same signature as `func` and preserves `this`.
 *
 * @example
 * const search = debounce((query: string) => fetchResults(query), 300);
 * input.addEventListener('input', (e) => search(e.target.value));
 */
export function debounce<T extends (...args: any[]) => void>(func: T, wait: number): T {
    let timeout: ReturnType<typeof setTimeout> | null = null;

    return function (this: any, ...args: Parameters<T>) {
        if (timeout) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(() => {
            func.apply(this, args);
            timeout = null;
        }, wait);
    } as T;
}
