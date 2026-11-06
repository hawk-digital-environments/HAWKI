<!--
  @component markstream-svelte custom component for `math_block` nodes. Wraps
  the package's own MathBlockNode, so rendering (KaTeX worker, fallbacks,
  MathML for screen readers) stays exactly as-is, and adds a hover-reveal
  button that copies the raw LaTeX source. Restores the copy affordance the
  legacy renderer had — the old `.math` / `dataset.rawmath` code never matched
  markstream's DOM and was removed; this is its markstream-native replacement.
-->
<script lang="ts">
    import type {SvelteRenderableNode, SvelteRenderContext} from 'markstream-svelte';
    import {MathBlockNode} from 'markstream-svelte';
    import Copy01Icon from '$lib/components/ui/icons/iconset/Copy01Icon.svelte';
    import CopyCheckIcon from '$lib/components/ui/icons/iconset/CopyCheckIcon.svelte';
    import {__} from '$lib/utils/translator.js';
    import {onDestroy} from 'svelte';

    interface Props {
        /** The parsed math node; `raw` / `content` / `markup` hold the LaTeX source. */
        node: SvelteRenderableNode;
        /** Render context, accepted for signature parity with the other extensions. */
        context?: SvelteRenderContext;
        /** Position key, accepted for signature parity with the other extensions. */
        indexKey?: string | number;
    }

    const {node}: Props = $props();

    // Same source derivation as the package's MathBlockNode: the LaTeX text
    // is not kept in the rendered DOM, so read it from the parsed node.
    const rawLatex = $derived(String((node as any)?.raw ?? (node as any)?.content ?? (node as any)?.markup ?? ''));

    let copied = $state(false);
    let resetTimer: ReturnType<typeof setTimeout> | undefined;

    onDestroy(() => clearTimeout(resetTimer));

    async function copyLatex() {
        try {
            await navigator.clipboard.writeText(rawLatex);
            copied = true;
            clearTimeout(resetTimer);
            resetTimer = setTimeout(() => (copied = false), 1500);
        } catch {
            // Clipboard access can be denied (e.g. unfocused document); the
            // rendered formula stays selectable as the fallback.
        }
    }
</script>

<div class="math-node">
    <MathBlockNode {node} />
    <button
        type="button"
        class="math-copy-btn"
        class:copied
        onclick={copyLatex}
        aria-label={copied ? __('markdown.markstream.common.copied') : __('markdown.markstream.common.copy')}
        title={copied ? __('markdown.markstream.common.copied') : __('markdown.markstream.common.copy')}
    >
        {#if copied}
            <CopyCheckIcon size={14}/>
        {:else}
            <Copy01Icon size={14}/>
        {/if}
    </button>
</div>

<style>
    .math-node {
        position: relative;
    }

    .math-copy-btn {
        position: absolute;
        top: 0.25rem;
        right: 0.25rem;
        z-index: 1;

        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.3rem;

        border: var(--border-stroke-thin);
        border-radius: var(--border-radius-tight);
        background-color: var(--color-surface-light);
        color: var(--color-text-muted);

        opacity: 0;
        transition: opacity var(--transition-fast), color var(--transition-fast);
    }

    .math-node:hover .math-copy-btn,
    .math-copy-btn:focus-visible,
    .math-copy-btn.copied {
        opacity: 1;
    }

    .math-copy-btn:hover {
        color: var(--color-text);
    }

    .math-copy-btn.copied {
        color: var(--color-success);
    }
</style>
