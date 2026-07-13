import type { AssistantKey } from '$plugins/assistants/types/assistant';
import type { AiModel } from '$plugins/core/schemas/resources/ai-models.schema.js';

/**
 * Fields that describe *which* assistant this is rather than *what the user
 * configured* — server-owned bookkeeping and back-references. They are skipped
 * by every change-diff, so restoring a draft or refetching a record never
 * registers as an unsaved edit.
 */
export const IDENTITY_KEYS: ReadonlySet<AssistantKey> = new Set<AssistantKey>([
    'id',
    'createdAt',
    'updatedAt',
    'creator',
    'versions',
    'actionPermissions',
    'isFavorite',
    // The remix back-reference: set once by the server when the draft is
    // minted, never edited in the builder.
    'remixedAssistant'
]);

export function isPlainObject(v: unknown): v is Record<string, unknown> {
    return typeof v === 'object' && v !== null && !Array.isArray(v);
}

/**
 * The model's hard cap on output tokens (`limits.max_output_tokens`), or `null`
 * when the model doesn't declare a usable one. Only `model_type: 'chat'` models
 * validate `limits` strictly; other types carry it as `unknown`, so the field
 * is read defensively instead of trusting the union type.
 */
export function getMaxOutputTokensLimit(model: AiModel): number | null {
    if (!isPlainObject(model.limits)) return null;
    const max = model.limits['max_output_tokens'];
    return typeof max === 'number' && Number.isFinite(max) && max > 0 ? max : null;
}

/**
 * Structural value equality across the shapes an assistant field can hold
 * (primitives, string[], nested value objects like Category/Tag[]).
 */
export function valuesEqual(a: unknown, b: unknown): boolean {
    if (a === b) return true;
    if (Array.isArray(a) && Array.isArray(b)) {
        return a.length === b.length && a.every((v, i) => valuesEqual(v, b[i]));
    }
    if (isPlainObject(a) && isPlainObject(b)) {
        const keys = Object.keys(a);
        return keys.length === Object.keys(b).length
            && keys.every(k => valuesEqual(a[k], b[k]));
    }
    return false;
}

/**
 * Keys that hold a live browser handle rather than data. They are dropped when
 * a draft is written to session storage: `JSON.stringify` turns them into `{}`,
 * which would then fail validation on the way back in (see
 * {@link import('./BuilderContext.svelte').BuilderContext.restoreFromSession}).
 */
const TRANSIENT_KEYS = new Set(['file', 'abortController']);

/** `JSON.stringify` replacer that omits {@link TRANSIENT_KEYS}. */
export function omitTransient(key: string, value: unknown): unknown {
    return TRANSIENT_KEYS.has(key) ? undefined : value;
}

/**
 * Deep copy used for the draft and baseline.
 *
 * Assistant fields are all JSON-serializable (strings, numbers, booleans,
 * arrays, plain objects), so a JSON round-trip is a safe deep clone. We use it
 * deliberately instead of `structuredClone`: a freshly created draft can still
 * carry a Svelte `$state` proxy — e.g. the default `category`, which references
 * the reactive `assistantOptionsStore` — and `structuredClone` throws
 * `DataCloneError` on reactive proxies. Serializing reads straight through the
 * proxy, so it just works (and detaches the copy from the store).
 */
export function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
}
