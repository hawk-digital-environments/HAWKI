import {getContext, setContext} from 'svelte';

/**
 * Parent-child context wiring for `RadioRowGroup` (parent) and `RadioRow`
 * (child) — a lightweight alternative to `runed`'s `Context` built directly
 * on Svelte's `getContext`/`setContext`.
 *
 * WHY: `RadioRow` needs to read/set the group's current value, know whether
 * the whole group is disabled, share the group's `name` for its hidden radio
 * input, and know whether it is the group's single Tab stop — without
 * `RadioRowGroup` passing callbacks down through props/slots. All accessors
 * are backed by getter/setter functions (not plain values) so a `RadioRow`
 * re-derives from the group's live `$state` on every read instead of
 * capturing a stale snapshot.
 *
 * HOW TO USE: `RadioRowGroup` calls `createRadioRowContext(...)` once in its
 * `<script>` to publish its state; each `RadioRow` calls `getRadioRowContext()`
 * to read it and `registerOption()` to take part in the Tab stop. Don't
 * construct `RadioRowContext` directly — go through `createRadioRowContext`.
 */
export class RadioRowContext {
    /** Registered row values (in mount order) mapped to whether each is disabled. */
    #options = $state<Record<string, boolean>>({});

    constructor(
        private valueGetter: () => string,
        private valueSetter: (newValue: string) => void,
        private disabledGetter: () => boolean,
        private nameGetter: () => string | undefined
    ) {
    }

    /** Current selected value of the group. */
    public get value(): string {
        return this.valueGetter();
    }

    /** Select a new value. No-op when it equals the current value. */
    public set value(newValue: string) {
        this.valueSetter(newValue);
    }

    /** Whether the whole group is disabled. */
    public get isDisabled(): boolean {
        return this.disabledGetter();
    }

    /** The shared `name` for the underlying radio inputs. */
    public get name(): string | undefined {
        return this.nameGetter();
    }

    /**
     * The value of the row that takes the group's single Tab stop: the selected
     * row, or the first enabled one when the selection is missing or disabled.
     */
    public get tabStop(): string | undefined {
        if (this.isDisabled) return undefined;
        const value = this.value;
        if (this.#options[value] === false) return value;
        return Object.keys(this.#options).find(key => !this.#options[key]);
    }

    /** Registers (or updates) a row; returns the matching unregister call. */
    public registerOption(value: string, disabled: boolean): () => void {
        this.#options[value] = disabled;
        return () => {
            delete this.#options[value];
        };
    }
}

const radioRowContextKey = Symbol('radio-row');

/**
 * Reads the nearest ancestor `RadioRowContext`. Call from a `RadioRow`'s
 * `<script>`. Throws if no `RadioRowGroup` is found above it in the tree —
 * `RadioRow` is not usable standalone.
 */
export function getRadioRowContext(): RadioRowContext {
    const context = getContext<RadioRowContext>(radioRowContextKey);
    if (!context) {
        throw new Error('RadioRowContext not found. Make sure you are using a RadioRowGroup.');
    }
    return context;
}

/**
 * Creates and publishes a `RadioRowContext` for descendant `RadioRow`s.
 * Call once from `RadioRowGroup`'s `<script>`, passing getters/setters
 * backed by its own `$state` (or bindable props) so children stay reactive.
 */
export function createRadioRowContext(
    valueGetter: () => string,
    valueSetter: (newValue: string) => void,
    disabledGetter: () => boolean,
    nameGetter: () => string | undefined
): RadioRowContext {
    const context = new RadioRowContext(valueGetter, valueSetter, disabledGetter, nameGetter);
    setContext(radioRowContextKey, context);
    return context;
}
