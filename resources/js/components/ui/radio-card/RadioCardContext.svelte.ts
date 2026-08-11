import {getContext, setContext} from 'svelte';

/**
 * Parent-child context wiring for `RadioCardGroup` (parent) and `RadioCard`
 * (child) — a lightweight alternative to `runed`'s `Context` built directly
 * on Svelte's `getContext`/`setContext`.
 *
 * WHY: `RadioCard` needs to read/set the group's current value, know whether
 * the whole group is disabled, and share the group's `name` for its hidden
 * radio input — without `RadioCardGroup` passing callbacks down through
 * props/slots. All accessors are backed by getter/setter functions (not
 * plain values) so a `RadioCard` re-derives from the group's live `$state`
 * on every read instead of capturing a stale snapshot.
 *
 * HOW TO USE: `RadioCardGroup` calls `createRadioCardContext(...)` once in
 * its `<script>` to publish its state; each `RadioCard` calls
 * `getRadioCardContext()` to read it. Don't construct `RadioCardContext`
 * directly — go through `createRadioCardContext`.
 */
export class RadioCardContext {
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
}

const radioCardContextKey = Symbol('radio-card');

/**
 * Reads the nearest ancestor `RadioCardContext`. Call from a `RadioCard`'s
 * `<script>`. Throws if no `RadioCardGroup` is found above it in the tree —
 * `RadioCard` is not usable standalone.
 */
export function getRadioCardContext(): RadioCardContext {
    const context = getContext<RadioCardContext>(radioCardContextKey);
    if (!context) {
        throw new Error('RadioCardContext not found. Make sure you are using a RadioCardGroup.');
    }
    return context;
}

/**
 * Creates and publishes a `RadioCardContext` for descendant `RadioCard`s.
 * Call once from `RadioCardGroup`'s `<script>`, passing getters/setters
 * backed by its own `$state` (or bindable props) so children stay reactive.
 */
export function createRadioCardContext(
    valueGetter: () => string,
    valueSetter: (newValue: string) => void,
    disabledGetter: () => boolean,
    nameGetter: () => string | undefined
): RadioCardContext {
    const context = new RadioCardContext(valueGetter, valueSetter, disabledGetter, nameGetter);
    setContext(radioCardContextKey, context);
    return context;
}
