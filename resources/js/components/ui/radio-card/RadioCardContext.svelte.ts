import {getContext, setContext} from 'svelte';

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

export function getRadioCardContext(): RadioCardContext {
    const context = getContext<RadioCardContext>(radioCardContextKey);
    if (!context) {
        throw new Error('RadioCardContext not found. Make sure you are using a RadioCardGroup.');
    }
    return context;
}

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
