/**
 * Focuses a composer menu row and scrolls it into the menu's viewport — shared by
 * `AssistantMenu` and `ModelPickerV2` so both open on their current choice the same way.
 *
 * Minimal scroll: stay at the top while the row is visible there; otherwise scroll just far
 * enough to rest it on the bottom edge, keeping the view's bottom padding so it doesn't sit
 * flush against the panel border.
 *
 * @param row The row to focus.
 * @param panelSelector The menu content's class selector, whose max-height can clip the viewport.
 */
export function revealMenuRow(row: HTMLElement, panelSelector: string): void {
    row.focus({preventScroll: true});
    const viewport = row.closest<HTMLElement>('.viewport');
    if (!viewport) {
        return;
    }
    // Rects are read mid open animation, while the panel is still scaled down, so
    // convert them back to layout pixels before mixing with scroll offsets.
    const viewportRect = viewport.getBoundingClientRect();
    const scale = viewportRect.height / viewport.offsetHeight || 1;
    const rowTop = (row.getBoundingClientRect().top - viewportRect.top) / scale + viewport.scrollTop;
    // The panel's max-height can clip the viewport, so only count what is visible.
    const panel = viewport.closest<HTMLElement>(panelSelector);
    const visibleHeight = panel
        ? Math.min(viewport.clientHeight, (panel.getBoundingClientRect().bottom - viewportRect.top) / scale)
        : viewport.clientHeight;
    const view = row.closest<HTMLElement>('.view');
    const gap = view ? parseFloat(getComputedStyle(view).paddingBottom) || 0 : 0;
    const rowBottom = rowTop + row.offsetHeight + gap;
    viewport.scrollTop = rowBottom <= visibleHeight ? 0 : rowBottom - visibleHeight;
}
