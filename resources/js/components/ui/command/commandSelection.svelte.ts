/**
 * The selection state machine behind `CommandSearch`.
 *
 * It keeps two values apart: {@link CommandSelection.value} is the command
 * root's value — the row bits-ui highlights and points
 * `aria-activedescendant` at — while {@link CommandSelection.selection} is
 * the row the *user* is on. bits-ui moves `value` for its own bookkeeping as
 * well (it re-selects the first row whenever rows register), so the machine
 * classifies every value change before accepting it:
 *
 * - inside a **gesture window** — opened by a keyboard event, closed on the
 *   next microtask, because bits-ui applies its own re-selection in a
 *   microtask after a DOM flush — the change is intent: `selection` follows.
 * - inside a **selection transaction** — opened when a row is pressed or
 *   Enter is struck, closed when the row's `onSelect` runs — the change is
 *   the selection itself (bits-ui sets the value *before* invoking the row's
 *   callback).
 * - any other change is bookkeeping. While the host's order is frozen the
 *   user's row is written back, so a late group cannot steal the highlight;
 *   otherwise the bookkeeping stands.
 *
 * Relies on bits-ui 2.18.1 behaviour — the version is pinned: host handlers
 * merge ahead of the command's own, `onValueChange` fires synchronously from
 * `setValue`, and the post-registration re-selection happens after a
 * microtask. An upgrade that changes any of these shows up here first.
 */

/** How long a selection transaction may stay open without a delivered selection. */
const TRANSACTION_FALLBACK_MS = 500;

export interface CommandSelectionOptions {
    /** Whether the host's row order is currently frozen. */
    isFrozen(): boolean;
    /** The row values that currently exist and are selectable. */
    selectableValues(): ReadonlySet<string>;
    /** Overrides the transaction fallback; tests use milliseconds. */
    fallbackMs?: number;
}

export class CommandSelection {
    /** The command root's value: the highlighted row, `''` when none. */
    public value = $state('');
    /** The row the user is on — survives bits-ui's bookkeeping. */
    public selection = $state('');

    private readonly options: CommandSelectionOptions;
    private gesture = false;
    private transaction = false;
    private transactionTimer: ReturnType<typeof setTimeout> | null = null;

    public constructor(options: CommandSelectionOptions) {
        this.options = options;
    }

    /** A keyboard event is being processed; value changes until the next microtask are intent. */
    public noteKeyboard(): void {
        this.gesture = true;
        queueMicrotask(() => (this.gesture = false));
    }

    /** Real pointer motion onto a row is intent, and moves the user there. */
    public notePointer(value: string): void {
        this.selection = value;
        this.value = value;
    }

    /**
     * A row was pressed or Enter was struck: the value change that carries
     * the selection is imminent (bits-ui sets the value before the row's
     * `onSelect` runs).
     */
    public beginTransaction(): void {
        this.transaction = true;
        if (this.transactionTimer !== null) {
            clearTimeout(this.transactionTimer);
        }
        this.transactionTimer = setTimeout(() => {
            this.transactionTimer = null;
            this.transaction = false;
        }, this.options.fallbackMs ?? TRANSACTION_FALLBACK_MS);
    }

    /** The selection has been delivered (or will never come); the transaction closes. */
    public endTransaction(): void {
        this.transaction = false;
        if (this.transactionTimer !== null) {
            clearTimeout(this.transactionTimer);
            this.transactionTimer = null;
        }
    }

    /** bits-ui's `onValueChange`: intent is accepted, bookkeeping is corrected while frozen. */
    public acceptValueChange(value: string): void {
        if (this.gesture || this.transaction) {
            this.selection = value;
            return;
        }
        if (this.options.isFrozen()) {
            this.value = this.options.selectableValues().has(this.selection) ? this.selection : '';
        }
    }

    /**
     * The set of rows changed: a selection whose row vanished is dropped,
     * along with the highlight; one that survived is re-asserted over the
     * bookkeeping re-selection of the first row.
     */
    public syncRows(): void {
        if (this.selection === '') {
            return;
        }
        if (!this.options.selectableValues().has(this.selection)) {
            this.selection = '';
            this.value = '';
            return;
        }
        if (!this.gesture && this.value !== this.selection) {
            this.value = this.selection;
        }
    }

    /** A changed input (query or scope): highlight and selection start over. */
    public reset(): void {
        this.selection = '';
        this.value = '';
        this.endTransaction();
    }
}

/** Whether a keyboard event moves the highlight: arrows, Home/End, Ctrl + vim keys. */
export function isNavigationKey(event: KeyboardEvent): boolean {
    const navigationKeys = new Set(['ArrowDown', 'ArrowUp', 'Home', 'End', 'PageDown', 'PageUp']);
    const vimKeys = new Set(['n', 'p', 'j', 'k']);
    return navigationKeys.has(event.key) || (event.ctrlKey && vimKeys.has(event.key.toLowerCase()));
}
