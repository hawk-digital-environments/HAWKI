/**
 * Focus registry for the tool picker dropdown.
 *
 * Each focusable element (tool row, MCP group info trigger) registers itself
 * here on mount, so siblings can navigate by component identity instead of
 * DOM queries.
 */
import {getContext, setContext} from 'svelte';

export type ToolMenuFocusKind = 'tool' | 'group-info';

export interface ToolMenuFocusEntry {
    key: string;
    kind: ToolMenuFocusKind;
    element: HTMLElement;
}

export class ToolMenuFocusContext {
    private entries = new Map<string, ToolMenuFocusEntry>();

    /** Registers a focusable element. Returns an unregister function. */
    public register(key: string, element: HTMLElement, kind: ToolMenuFocusKind): () => void {
        this.entries.set(key, {key, element, kind});
        return () => {
            const current = this.entries.get(key);
            if (current && current.element === element) {
                this.entries.delete(key);
            }
        };
    }

    /** Focuses the entry with the given key, if present and not disabled. */
    public focusByKey(key: string): boolean {
        const entry = this.entries.get(key);
        if (!entry || this.isDisabled(entry.element)) {
            return false;
        }
        entry.element.focus();
        return true;
    }

    /** Focuses the first registered entry matching the predicate (in DOM order). */
    public focusFirst(predicate?: (entry: ToolMenuFocusEntry) => boolean): boolean {
        const ordered = this.orderedEntries();
        const target = ordered.find(e => !this.isDisabled(e.element) && (!predicate || predicate(e)));
        if (!target) {
            return false;
        }
        target.element.focus();
        return true;
    }

    /** Returns the entry adjacent to `currentKey` in DOM order, skipping disabled. */
    public getAdjacent(currentKey: string, direction: 1 | -1): ToolMenuFocusEntry | null {
        const ordered = this.orderedEntries().filter(e => !this.isDisabled(e.element));
        if (ordered.length === 0) {
            return null;
        }
        const index = ordered.findIndex(e => e.key === currentKey);
        if (index === -1) {
            return null;
        }
        return ordered[(index + direction + ordered.length) % ordered.length];
    }

    /** Focuses the adjacent entry. Returns false if there is no neighbor to move to. */
    public focusAdjacent(currentKey: string, direction: 1 | -1): boolean {
        const next = this.getAdjacent(currentKey, direction);
        if (!next) {
            return false;
        }
        next.element.focus();
        return true;
    }

    private orderedEntries(): ToolMenuFocusEntry[] {
        return Array.from(this.entries.values()).sort((a, b) => {
            const pos = a.element.compareDocumentPosition(b.element);
            if (pos & Node.DOCUMENT_POSITION_FOLLOWING) return -1;
            if (pos & Node.DOCUMENT_POSITION_PRECEDING) return 1;
            return 0;
        });
    }

    private isDisabled(element: HTMLElement): boolean {
        return element.hasAttribute('data-disabled') || element.matches(':disabled');
    }
}

const toolMenuFocusContextKey = Symbol('toolMenuFocus');

export function setToolMenuFocusContext(): ToolMenuFocusContext {
    const context = new ToolMenuFocusContext();
    setContext(toolMenuFocusContextKey, context);
    return context;
}

export function useToolMenuFocusContext(): ToolMenuFocusContext {
    const context = getContext<ToolMenuFocusContext>(toolMenuFocusContextKey);
    if (!context) {
        throw new Error('useToolMenuFocusContext has no access to ToolMenuFocusContext.');
    }
    return context;
}
