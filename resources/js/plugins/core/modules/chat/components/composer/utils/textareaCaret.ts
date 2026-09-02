/**
 * Caret geometry for `<textarea>` elements.
 *
 * The DOM exposes no API for "where is the caret on screen", so we mirror the textarea
 * into an off-screen `<div>` that copies every layout-relevant style, put a marker span
 * at the caret offset, and measure that. This is the standard mirror-div technique; it is
 * accurate as long as the copied properties below cover everything affecting text layout.
 *
 * Used by `ComposerTextarea` to anchor the `@` mention popup to the caret.
 */

/** Style properties that influence text layout and therefore must be mirrored. */
const MIRRORED_PROPERTIES = [
    'boxSizing',
    'width',
    'borderTopWidth',
    'borderRightWidth',
    'borderBottomWidth',
    'borderLeftWidth',
    'paddingTop',
    'paddingRight',
    'paddingBottom',
    'paddingLeft',
    'fontStyle',
    'fontVariant',
    'fontWeight',
    'fontStretch',
    'fontSize',
    'fontFamily',
    'lineHeight',
    'letterSpacing',
    'wordSpacing',
    'textTransform',
    'textIndent',
    'textAlign',
    'whiteSpace',
    'wordBreak',
    'overflowWrap',
    'tabSize'
] as const;

/** Viewport-relative position and height of the caret. */
export interface CaretRect {
    /** Distance from the viewport's left edge, in px. */
    left: number;
    /** Distance from the viewport's top edge to the caret line's top, in px. */
    top: number;
    /** Height of the caret line, in px — add it to `top` to get the line's bottom. */
    height: number;
}

/**
 * Returns the viewport-relative rect of the caret (or of `position`, when given) inside
 * `textarea`. The returned rect scrolls with the page, exactly like `getBoundingClientRect`.
 */
export function getTextareaCaretRect(textarea: HTMLTextAreaElement, position?: number): CaretRect {
    const offset = position ?? textarea.selectionStart ?? 0;
    const computed = window.getComputedStyle(textarea);

    const mirror = document.createElement('div');
    mirror.style.position = 'absolute';
    mirror.style.visibility = 'hidden';
    mirror.style.top = '0';
    mirror.style.left = '-9999px';
    // The textarea itself scrolls; the mirror must not, so it lays out its full height.
    mirror.style.overflow = 'hidden';
    mirror.style.height = 'auto';

    for (const property of MIRRORED_PROPERTIES) {
        mirror.style[property] = computed[property];
    }

    // Text before the caret, then a zero-width marker we can measure.
    mirror.textContent = textarea.value.slice(0, offset);
    const marker = document.createElement('span');
    // A non-empty node so the span gets a box even at the very end of the text.
    marker.textContent = textarea.value.slice(offset) || '.';
    mirror.appendChild(marker);

    document.body.appendChild(mirror);
    const markerTop = marker.offsetTop;
    const markerLeft = marker.offsetLeft;
    const lineHeight = parseFloat(computed.lineHeight) || marker.offsetHeight;
    document.body.removeChild(mirror);

    const textareaRect = textarea.getBoundingClientRect();
    return {
        left: textareaRect.left + markerLeft - textarea.scrollLeft,
        top: textareaRect.top + markerTop - textarea.scrollTop,
        height: lineHeight
    };
}
