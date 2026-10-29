/**
 * Content-panel props that keep pointer and keyboard navigation separate: hovering a row
 * must not move focus. bits-ui focuses the row under the mouse on pointermove and the
 * panel on pointerleave, which pulls focus out of e.g. a search field and paints the
 * panel's focus ring.
 *
 * pointermove honours `defaultPrevented`; pointerleave isn't cancelable, so it's kept from
 * reaching the rows instead. Submenu triggers still need their leave event to cancel a
 * pending open, and the panel's own leave drives bits-ui's submenu grace handling.
 */
function preventHoverFocus(event: PointerEvent) {
    if (event.pointerType === 'mouse') event.preventDefault();
}

function blockRowLeave(event: PointerEvent) {
    if (event.pointerType !== 'mouse' || event.target === event.currentTarget) return;
    if (event.target instanceof Element && event.target.closest('.dropdown-sub-trigger')) return;
    event.stopPropagation();
}

export const noHoverFocusProps = {
    onpointermovecapture: preventHoverFocus,
    onpointerleavecapture: blockRowLeave
};
