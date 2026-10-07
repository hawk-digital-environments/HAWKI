/**
 * Reports whether an element's content overflows its box, per axis, and keeps
 * reporting on element resize (e.g. a card grid track narrowing). The 1px
 * tolerance keeps fractional layout rounding from counting as overflow.
 *
 * Measures once immediately and on every ResizeObserver callback; re-invoke
 * (e.g. from an `$effect` keyed on the content) to re-measure on content
 * changes — the returned cleanup disconnects the observer.
 *
 * @example
 * $effect(() => {
 *     void text;
 *     const el = untrack(() => textEl);
 *     if (!el) return;
 *     return observeOverflow(el, ({y}) => truncated = y);
 * });
 */
export function observeOverflow(
    el: HTMLElement,
    onChange: (overflow: {x: boolean; y: boolean}) => void
): () => void {
    const measure = () => onChange({
        x: el.scrollWidth > el.clientWidth + 1,
        y: el.scrollHeight > el.clientHeight + 1
    });
    measure();

    const observer = new ResizeObserver(measure);
    observer.observe(el);
    return () => observer.disconnect();
}
