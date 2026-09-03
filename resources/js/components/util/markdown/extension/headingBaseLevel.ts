import {getContext, setContext} from 'svelte';

const key = Symbol('markdown-heading-base-level');

/**
 * Provides the heading level a `#` in the rendered markdown maps to.
 * Called by `Markdown.svelte`; read by the custom `HeadingNode`.
 */
export function provideMarkdownHeadingBaseLevel(read: () => number): void {
    setContext(key, read);
}

/** Reads the base level provided by the nearest `Markdown` component (1 when none is set). */
export function useMarkdownHeadingBaseLevel(): () => number {
    return getContext<(() => number) | undefined>(key) ?? (() => 1);
}
