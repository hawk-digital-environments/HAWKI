interface GlobModuleLoaderOptions<T, TM = T> {
    /**
     * Derives the result map's key from a module's file path (the key that
     * Vite's `import.meta.glob()` uses, e.g. `/src/config/theme.schema.ts`).
     * Defaults to using the raw file path as the key. Throwing inside this
     * function (e.g. on an unexpected filename shape) aborts the whole call.
     *
     * @example
     * keyResolver: (filename) => filename.match(/\/([\w-]+)\.schema\.ts$/)![1]
     */
    keyResolver?: (filename: string) => string;
    /**
     * Which export(s) on the loaded module to treat as the value, tried in
     * order until one passes `validate`. Defaults to `'default'`. Pass an
     * array to accept multiple export conventions, e.g. `['default', 'schema']`
     * to support both `export default schema` and `export const schema = …`.
     */
    valueKey?: string | string[];
    /**
     * Guards against picking up an export that merely happens to exist under
     * `valueKey` but isn't the right shape (e.g. a stray `default` export).
     * Defaults to accepting anything truthy. Return `false` to make
     * `globModuleLoader` try the next `valueKey`, or throw for all of them.
     *
     * @example
     * validate: (value) => !!value && typeof (value as any).parse === 'function'
     */
    validate?: (value: Partial<T> | undefined, filename: string, key: string) => boolean;
    /**
     * Transforms the validated value before it is stored under the resolved
     * key. Defaults to the identity function. Use this when the loader's
     * declared type (`T`) is only an intermediate shape you need to convert
     * (e.g. wrapping a raw async migration function in a richer object).
     */
    mapper?: (module: T, filename: string, key: string) => TM;
}

/**
 * Normalizes the result of Vite's `import.meta.glob()` into a flat,
 * de-duplicated `Record<key, value>` map, so kernel/plugin registrars
 * (config schemas, resource schemas, migrations, …) don't each reimplement
 * "pick an export off every matched file and key it by something derived
 * from the path."
 *
 * Works with both glob modes:
 * - **Eager** (`import.meta.glob('...', {eager: true})`) — `modules` is
 *   `Record<filename, module>`; the result is `Record<key, value>`.
 * - **Lazy** (`import.meta.glob('...')`) — `modules` is
 *   `Record<filename, () => Promise<module>>`; the result is
 *   `Record<key, () => Promise<value>>`, i.e. each entry stays lazy and is
 *   only resolved/validated the first time it's awaited.
 *
 * For every matched file it: derives a key via `keyResolver` (default: the
 * raw filename), extracts a candidate value from one of `valueKey`'s export
 * names (default: `'default'`), validates it via `validate`, and optionally
 * transforms it via `mapper`. Throws if two files resolve to the same key,
 * if a module isn't an object, or if none of the `valueKey` candidates pass
 * validation — so a misnamed export fails loudly at startup instead of
 * silently registering nothing.
 *
 * @example
 * // Eager: collect every `*.schema.ts` file's `default` or `schema` export,
 * // keyed by the namespace segment of its filename.
 * const modules = import.meta.glob('./config/*.schema.ts', {eager: true});
 * const schemas = globModuleLoader(modules, {
 *     keyResolver: (filename) => filename.match(/\/([\w-]+)\.schema\.ts$/)![1],
 *     valueKey: ['default', 'schema'],
 *     validate: (v) => !!v && typeof (v as any).parse === 'function',
 * });
 *
 * @example
 * // Lazy: keep each migration's loader deferred; only `migrate` exports pass.
 * const modules = import.meta.glob('./migrations/**\/*.ts');
 * const loaders = globModuleLoader(modules, {
 *     valueKey: 'migrate',
 *     validate: (v) => typeof v === 'function',
 * });
 * await loaders['./migrations/2024_01_01_init.ts'](); // resolves lazily
 *
 * @param modules Either the eager glob result (`Record<filename, module>`) or
 *   the lazy one (`Record<filename, () => Promise<module>>`) — the correct
 *   overload/return shape is inferred from which one you pass.
 * @param options See {@link GlobModuleLoaderOptions}.
 */
export function globModuleLoader<T, TM = T>(
    modules: Record<string, T>,
    options?: GlobModuleLoaderOptions<T, TM>
): Record<string, TM>;
export function globModuleLoader<T, TM = T>(
    modules: Record<string, () => Promise<T>>,
    options?: GlobModuleLoaderOptions<T, TM>
): Record<string, () => Promise<TM>>;
export function globModuleLoader<T, TM = T>(
    modules: (Record<string, T> | Record<string, () => Promise<T>>),
    options?: GlobModuleLoaderOptions<T, TM>
) {
    const keyResolver = options?.keyResolver ?? ((filename: string) => filename);
    const validator = options?.validate ?? (() => true);
    const mapper = options?.mapper ?? ((module: T) => module as unknown as TM);
    const result: Record<string, TM> | Record<string, () => Promise<TM>> = {};
    const valueKeys = options?.valueKey ? (Array.isArray(options.valueKey) ? options.valueKey : [options.valueKey]) : ['default'];

    const isAsync = Object.values(modules).some((module) => typeof module === 'function');

    function getValidatedValue(module: T, filename: string, key: string) {
        if (typeof module !== 'object' || module === null) {
            throw new Error(`Module '${filename}' is not an object, cannot extract value for key '${key}'`);
        }

        for (const valueKey of valueKeys) {
            const value = (module as any)[valueKey] as Partial<T> | undefined;
            if (value && validator(value, filename, key)) {
                return mapper(value as T, filename, key);
            }
        }

        throw new Error(`No valid export found for module '${filename}'! Checked the exports with keys: ${valueKeys.join(', ')}`);
    }

    const keyFileMap = new Map<string, string>();

    for (const [filename, moduleOrLoader] of Object.entries(modules)) {
        const key = keyResolver(filename);

        if (key in result) {
            const existingFile = keyFileMap.get(key)!;
            throw new Error(`Duplicate key '${key}' generated from files '${existingFile}' and '${filename}'. Please ensure unique keys or adjust the keyResolver.`);
        }
        keyFileMap.set(key, filename);

        if (isAsync) {
            result[key] = async () => {
                const module = await (moduleOrLoader as () => Promise<unknown>)();
                return getValidatedValue(module as T, filename, key);
            };
        } else {
            result[key] = getValidatedValue(moduleOrLoader as T, filename, key);
        }
    }

    return result;
}
