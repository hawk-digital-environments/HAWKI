/**
 * Shared "command mode" plumbing for the composer's `AssistantMenu` and `ToolMenu`: typing
 * `@` or `/` in `ComposerTextarea` opens the same menu as the button, but the textarea keeps
 * focus, the menu opens at the typed trigger instead of its button, and the typed query
 * stands in for the menu's search field.
 *
 * bits-ui moves its highlight with focus, and the menu content traps focus once it has it —
 * so instead of handing focus over, the textarea drives a virtual highlight here: the row it
 * points at carries `data-command-active` (styled by `DropdownMenuCheckboxItem`), ArrowUp/
 * ArrowDown move it and Enter/Tab click it, which runs the row's normal toggle.
 */

import type {CaretRect} from '$plugins/core/modules/chat/components/composer/utils/textareaCaret.js';

/** A typed `@query` / `/query` driving a menu from the message textarea. */
export interface MenuCommand {
    /** The text typed after the trigger; filters the menu instead of its search field. */
    query: string;
    /** Viewport position of the trigger character, which the menu opens at. */
    anchor: CaretRect;
}

/** What `ComposerTextarea` calls on the menu its command opened. */
export interface CommandMenuHandle {
    /** Moves the highlight by `delta` rows, wrapping around; false when there are none. */
    move(delta: number): boolean;
    /** Toggles the highlighted row; false when there is none. */
    selectActive(): boolean;
}

/**
 * Returns a function that keeps the last command until the menu is next opened without one.
 * The textarea drops its command the moment the menu closes, while the menu is still
 * animating out — without this the closing menu would snap back to its button, show its
 * search field and the unfiltered list again. Call it from a `$derived` in the menu.
 */
export function createCommandKeeper() {
    let last: MenuCommand | null = null;
    return (open: boolean, command: MenuCommand | null | undefined): MenuCommand | null => {
        if (command) {
            last = command;
        } else if (open) {
            // Opened from its button.
            last = null;
        }
        return last;
    };
}

const ROW_SELECTOR = '[role="menuitemcheckbox"]:not([data-disabled])';
const ACTIVE_ATTRIBUTE = 'data-command-active';

/**
 * @param contentSelector Selector of the menu's content element.
 * @param getCommand The menu's current `command` prop.
 * @param preferredRow The row the highlight starts on, if any (e.g. the tagged assistant).
 */
export function createCommandMenu(
    contentSelector: string,
    getCommand: () => MenuCommand | null | undefined,
    preferredRow: (content: ParentNode) => HTMLElement | null = () => null
) {
    // Whether the menu is open because of a command, decided when it opens — by the time it
    // closes, the textarea has already dropped the command.
    let session = false;

    function content(): HTMLElement | null {
        return document.querySelector<HTMLElement>(contentSelector);
    }

    function rows(): HTMLElement[] {
        return Array.from(content()?.querySelectorAll<HTMLElement>(ROW_SELECTOR) ?? []);
    }

    function activeRow(): HTMLElement | null {
        return content()?.querySelector<HTMLElement>(`[${ACTIVE_ATTRIBUTE}]`) ?? null;
    }

    function highlight(row: HTMLElement | null) {
        const current = activeRow();
        if (current === row) {
            return;
        }
        current?.removeAttribute(ACTIVE_ATTRIBUTE);
        row?.setAttribute(ACTIVE_ATTRIBUTE, '');
        row?.scrollIntoView({block: 'nearest'});
    }

    return {
        /** Content props that open the menu at the trigger rather than its button: above
         *  the line (the composer sits at the bottom of the screen), flipping below when
         *  there is no room. Empty outside command mode. */
        anchorProps() {
            const anchor = getCommand()?.anchor;
            if (!anchor) {
                return {};
            }
            return {
                customAnchor: {
                    getBoundingClientRect: () => new DOMRect(anchor.left, anchor.top, 0, anchor.height)
                },
                side: 'top' as const,
                align: 'start' as const
            };
        },

        /** The textarea keeps focus while a command opens the menu. */
        onOpenAutoFocus(event: Event) {
            session = !!getCommand();
            if (session) {
                event.preventDefault();
            }
        },

        /** The textarea takes focus back itself, so the trigger button must not. */
        onCloseAutoFocus(event: Event) {
            if (session) {
                session = false;
                event.preventDefault();
            }
        },

        /** Clicking into the textarea doesn't close the menu — moving the caret there is
         *  part of typing the command, and the textarea closes it once the caret leaves. */
        onInteractOutside(event: Event) {
            if (getCommand() && event.target instanceof HTMLTextAreaElement) {
                event.preventDefault();
            }
        },

        /** Hovering a row moves the highlight there, so Enter and a click can't target two
         *  different rows. */
        onMouseMove(event: MouseEvent) {
            if (!getCommand() || !(event.target instanceof Element)) {
                return;
            }
            const row = event.target.closest<HTMLElement>(ROW_SELECTOR);
            if (row) {
                highlight(row);
            }
        },

        /** Puts the highlight on the preferred row, or else the first one — call whenever the
         *  command opens the menu or its query changes the rows. */
        reset() {
            if (!getCommand()) {
                highlight(null);
                return;
            }
            const container = content();
            highlight((container ? preferredRow(container) : null) ?? rows()[0] ?? null);
        },

        move(delta: number): boolean {
            const all = rows();
            if (all.length === 0) {
                return false;
            }
            const index = all.indexOf(activeRow() as HTMLElement);
            const next = index === -1
                ? (delta > 0 ? 0 : all.length - 1)
                : (index + delta + all.length) % all.length;
            highlight(all[next]);
            return true;
        },

        selectActive(): boolean {
            const row = activeRow() ?? rows()[0];
            row?.click();
            return !!row;
        }
    };
}
