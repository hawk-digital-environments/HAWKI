import type {DataStore} from '$lib/kernel/stores/types.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'composer-pins': ComposerPinStore;
    }
}

/** The two things the composer menus can pin: assistants (by id) and tools (by name). */
export type ComposerPinKind = 'assistant' | 'tool';

const STORAGE_KEY = 'hawki.composer.pins';

/**
 * Reactive store for the assistants and tools a user has pinned in the composer menus.
 *
 * Pins are a per-browser display preference, not chat state: they only reorder
 * `AssistantMenu`/`ToolMenu`, lifting pinned rows into a "Pinned" section at the top of
 * the list. They are therefore kept in `localStorage` rather than on the server, and a
 * missing/corrupt entry simply means "nothing pinned".
 *
 * Pinned ids are kept in insertion order, so the pinned section reflects the order in
 * which the user pinned things.
 *
 * @example
 * const pinStore = useStore('composer-pins');
 * const pinned = $derived(pinStore.isPinned('tool', tool.name));
 * pinStore.toggle('tool', tool.name);
 */
export class ComposerPinStore implements DataStore {
    public readonly name = 'composer-pins';

    private _pins = $state<Record<ComposerPinKind, string[]>>(readPins());

    /** `true` when `id` is currently pinned for that kind. Reactive. */
    public isPinned(kind: ComposerPinKind, id: string): boolean {
        return this._pins[kind].includes(id);
    }

    /** The pinned ids for a kind, in the order they were pinned. Reactive. */
    public pinned(kind: ComposerPinKind): readonly string[] {
        return this._pins[kind];
    }

    /** Pins `id` (no-op if already pinned) and persists the change. */
    public pin(kind: ComposerPinKind, id: string): void {
        if (this.isPinned(kind, id)) {
            return;
        }
        this._pins = {...this._pins, [kind]: [...this._pins[kind], id]};
        writePins(this._pins);
    }

    /** Unpins `id` (no-op if not pinned) and persists the change. */
    public unpin(kind: ComposerPinKind, id: string): void {
        if (!this.isPinned(kind, id)) {
            return;
        }
        this._pins = {...this._pins, [kind]: this._pins[kind].filter(entry => entry !== id)};
        writePins(this._pins);
    }

    /** Pins `id` if it isn't pinned, unpins it otherwise. */
    public toggle(kind: ComposerPinKind, id: string): void {
        if (this.isPinned(kind, id)) {
            this.unpin(kind, id);
        } else {
            this.pin(kind, id);
        }
    }

    /**
     * Splits `items` into the pinned ones (in pin order) and the rest (in their original
     * order) — the shape both composer menus render.
     */
    public partition<T>(kind: ComposerPinKind, items: T[], idOf: (item: T) => string): {pinned: T[], rest: T[]} {
        const order = this._pins[kind];
        const pinned = items
            .filter(item => order.includes(idOf(item)))
            .sort((a, b) => order.indexOf(idOf(a)) - order.indexOf(idOf(b)));
        const rest = items.filter(item => !order.includes(idOf(item)));
        return {pinned, rest};
    }
}

function emptyPins(): Record<ComposerPinKind, string[]> {
    return {assistant: [], tool: []};
}

function readPins(): Record<ComposerPinKind, string[]> {
    if (typeof localStorage === 'undefined') {
        return emptyPins();
    }
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return emptyPins();
        }
        const parsed = JSON.parse(raw) as Partial<Record<ComposerPinKind, unknown>>;
        return {
            assistant: toIdList(parsed.assistant),
            tool: toIdList(parsed.tool)
        };
    } catch {
        // A user-editable storage key is never trusted: unreadable pins are simply no pins.
        return emptyPins();
    }
}

function toIdList(value: unknown): string[] {
    return Array.isArray(value) ? value.filter((entry): entry is string => typeof entry === 'string') : [];
}

function writePins(pins: Record<ComposerPinKind, string[]>): void {
    if (typeof localStorage === 'undefined') {
        return;
    }
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(pins));
    } catch {
        // Storage can be full or blocked (private mode); pins then just don't survive a reload.
    }
}
