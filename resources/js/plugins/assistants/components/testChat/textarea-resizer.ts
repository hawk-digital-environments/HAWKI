// lib/presets/utils/resize-textarea.ts

export function getScrollableParent(el: HTMLElement): HTMLElement | null {
    let parent = el.parentElement;
    while (parent) {
        const { overflowY } = getComputedStyle(parent);
        if (overflowY === 'auto' || overflowY === 'scroll') return parent;
        parent = parent.parentElement;
    }
    return null;
}

export function resizeTextarea(
    inputField: HTMLTextAreaElement,
    minHeight: number | null,
    allowShrink = false
): number {
    const scrollableParent = getScrollableParent(inputField);
    const savedScrollTop = scrollableParent?.scrollTop ?? window.scrollY;

    const currentHeight = inputField.offsetHeight;
    const newMinHeight = allowShrink
        ? (minHeight ?? currentHeight)
        : (minHeight === null || currentHeight > minHeight ? currentHeight : minHeight);

    inputField.style.height = 'auto';
    const newHeight = allowShrink
        ? inputField.scrollHeight
        : Math.max(inputField.scrollHeight, newMinHeight);
    inputField.style.height = newHeight + 'px';

    const { scrollTop, clientHeight, scrollHeight } = inputField;
    if (scrollHeight > clientHeight && scrollTop + clientHeight >= scrollHeight - 1) {
        inputField.scrollTop = scrollHeight;
    }

    if (scrollableParent) {
        scrollableParent.scrollTop = savedScrollTop;
    } else {
        window.scrollTo({ top: savedScrollTop, behavior: 'instant' });
    }

    return newMinHeight;
}

export function watchManualResize(
    inputField: HTMLTextAreaElement,
    onResized: (newMinHeight: number) => void
): () => void {
    function onMouseDown() {
        const heightBefore = inputField.offsetHeight;

        function onMouseUp() {
            if (inputField.offsetHeight !== heightBefore) {
                inputField.style.maxHeight = 'none';
                onResized(inputField.offsetHeight);
            }
            window.removeEventListener('mouseup', onMouseUp);
        }

        window.addEventListener('mouseup', onMouseUp);
    }

    inputField.addEventListener('mousedown', onMouseDown);
    return () => inputField.removeEventListener('mousedown', onMouseDown);
}