/**
 * Accordion coordination contract shared between `HelpPanel` (provider) and
 * `HelpElement` (consumers) via Svelte context.
 *
 * `HelpPanel` owns a single "active id"; each `HelpElement` registers with a
 * generated id and asks the panel whether it is the active one. Activating one
 * element collapses whichever was open — single-open accordion behaviour
 * without the panel needing to know about its (snippet-provided) children.
 */
export interface HelpAccordionContext {
    /** Is the element with this id the currently-open one? */
    isActive(id: string): boolean;
    /** Open this element (collapsing any other), or close it if already open. */
    toggle(id: string): void;
    /** Seed the initially-open element; the first requester wins. */
    requestInitial(id: string): void;
}

export const HELP_ACCORDION_KEY = Symbol('help-accordion');
