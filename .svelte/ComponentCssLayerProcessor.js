/**
 * Svelte preprocessor that automatically wraps non-layered component CSS
 * in `@layer components {}`, preserving any explicit `@layer` blocks as-is.
 *
 * Parsing strategy (handles ~95% of real-world cases):
 *   - Walk the CSS character-by-character, tracking brace depth.
 *   - A top-level block whose header matches `@layer ... {` is extracted as-is.
 *   - Everything else (at any depth) is collected into a single remainder.
 *   - The remainder, if non-empty, is wrapped in `@layer components {}`.
 *   - Block comments are skipped so `{` / `}` inside comments are ignored.
 *   - `<style global>` blocks are left completely untouched.
 *
 * Known limitations:
 *   - String literals containing `{` or `}` are not handled (rare in CSS).
 *   - `@layer` shorthand declarations (no block: `@layer foo;`) are treated as
 *     non-layer content and are forwarded inside `@layer components`, which is
 *     intentional — they are ordering declarations, not style blocks.
 */

/** @typedef {{ layerBlocks: string[], remaining: string }} SplitResult */

/**
 * Splits a CSS string into explicit top-level `@layer` blocks and everything else.
 *
 * @param {string} css
 * @returns {SplitResult}
 */
function splitCss(css) {
    const layerBlocks = [];
    const remainingParts = [];

    let i = 0;
    let depth = 0;
    let segmentStart = 0;
    let currentIsLayer = false;

    while (i < css.length) {
        // Skip block comments so their braces are invisible to the parser.
        if (css[i] === '/' && css[i + 1] === '*') {
            i += 2;
            while (i < css.length && !(css[i] === '*' && css[i + 1] === '/')) {
                i++;
            }
            i += 2; // step past '*/'
            continue;
        }

        if (css[i] === '{') {
            if (depth === 0) {
                // Everything from segmentStart up to this `{` is the block header.
                const header = css.slice(segmentStart, i).trim();
                currentIsLayer = /^@layer\s/.test(header);
            }
            depth++;
        } else if (css[i] === '}') {
            depth--;

            if (depth === 0) {
                const block = css.slice(segmentStart, i + 1);

                if (currentIsLayer) {
                    layerBlocks.push(block);
                } else {
                    remainingParts.push(block);
                }

                segmentStart = i + 1;
                currentIsLayer = false;
            }
        }

        i++;
    }

    // Capture any trailing content that has no enclosing block (e.g. bare
    // custom-property declarations, though unusual at component scope).
    const tail = css.slice(segmentStart).trim();
    if (tail) {
        remainingParts.push(tail);
    }

    return {
        layerBlocks,
        remaining: remainingParts.join('\n').trim(),
    };
}

/**
 * Returns a Svelte preprocessor group that places component styles in
 * `@layer components`, while leaving explicit `@layer` blocks untouched.
 *
 * @returns {import('@sveltejs/vite-plugin-svelte').PreprocessorGroup}
 */
export function componentCssLayerProcessor() {
    return {
        style({ content, attributes }) {
            // <style global> blocks are intentionally unlayered — skip them.
            if (attributes.global) return;

            const { layerBlocks, remaining } = splitCss(content);

            const parts = [...layerBlocks];

            if (remaining) {
                parts.push(`@layer components {\n${remaining}\n}`);
            }

            // Nothing changed (empty style block or only whitespace).
            if (parts.length === 0) return;

            return { code: parts.join('\n\n') };
        },
    };
}
