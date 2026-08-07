interface GlobModuleLoaderOptions<T, TM = T> {
    keyResolver?: (filename: string) => string;
    valueKey?: string | string[];
    validate?: (value: Partial<T> | undefined, filename: string, key: string) => boolean;
    mapper?: (module: T, filename: string, key: string) => TM;
}

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
