<!--
  @component markstream-svelte custom component for `link` nodes. Replaces the
  built-in LinkNode and routes each link through the appropriate HAWKI
  primitive based on its target:

  - Citation links (`#citation-…`, injected by `injectCitationsIntoMarkdown`)
    render as small numbered chips that smooth-scroll to the matching source
    tile and flash-highlight it.
  - Other hash links (`#…`) smooth-scroll to the target element on the page.
  - External http(s) links open in a new tab, get an automatic favicon
    (via `TextLink`), and show a rich URL preview on hover (`UrlPreviewTooltip`).
  - Same-origin and `mailto:` links render as a plain `TextLink`.
  - Anything else (e.g. `javascript:` URLs from untrusted markdown) is
    rendered as text only, without an anchor.

  Wire it up via `<MarkdownRender customComponents={{link: ExtendedLinkNode}} …/>`.
-->
<script lang="ts">
    import type {SvelteRenderableNode, SvelteRenderContext} from 'markstream-svelte';
    import {RenderChildren} from 'markstream-svelte';
    import TextLink from '$lib/components/util/link/TextLink.svelte';
    import UrlPreviewTooltip from '$lib/components/ui/tooltip/UrlPreviewTooltip.svelte';
    import {CITATION_ANCHOR_PREFIX, citationIdFromAnchorId} from '$lib/components/chat/message/injectCitationsIntoMarkdown.js';
    import CitationReference from '$lib/components/ui/citations/CitationReference.svelte';

    interface Props {
        /** The parsed markdown link node (href, title, children, text). */
        node: SvelteRenderableNode;
        /** Render context forwarded to child node renderers. */
        context?: SvelteRenderContext;
        /** Position key used to build stable child prefixes. */
        indexKey?: string | number;
    }

    const {node, context, indexKey}: Props = $props();

    const href = $derived(String((node as any)?.href ?? ''));
    const title = $derived(String((node as any)?.title ?? '') || undefined);
    const text = $derived(String((node as any)?.text ?? '') || href);
    const children = $derived.by(() => {
        const value = (node as any)?.children;
        return Array.isArray(value) ? value : [];
    });

    type LinkKind = 'citation' | 'hash' | 'external' | 'plain' | 'unsafe';

    const kind = $derived.by((): LinkKind => {
        if (href.startsWith(CITATION_ANCHOR_PREFIX)) {
            return 'citation';
        }
        if (href.startsWith('#')) {
            return 'hash';
        }
        try {
            const parsed = new URL(href, window.location.origin);
            if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
                return parsed.origin === window.location.origin ? 'plain' : 'external';
            }
            if (parsed.protocol === 'mailto:') {
                return 'plain';
            }
            return 'unsafe';
        } catch {
            return 'unsafe';
        }
    });

    function scrollToHashTarget(event: MouseEvent) {
        event.preventDefault();
        const rawId = href.slice(1);
        let decodedId = rawId;
        try {
            decodedId = decodeURIComponent(rawId);
        } catch {
            // keep the raw id if decoding fails
        }
        const target = document.getElementById(rawId)
            ?? (decodedId !== rawId ? document.getElementById(decodedId) : null);
        if (!target) {
            return;
        }
        target.scrollIntoView({behavior: 'smooth', block: 'start'});
    }
</script>

{#snippet linkContent()}
    {#if children.length}
        <RenderChildren nodes={children} context={context} prefix={String(indexKey ?? 'link') + '-link'}/>
    {:else}
        {text}
    {/if}
{/snippet}

{#if kind === 'citation'}
    <CitationReference citation={citationIdFromAnchorId(href)} title={title}>
        {@render linkContent()}
    </CitationReference>
{:else if kind === 'external'}
    <UrlPreviewTooltip url={href}>
        {#snippet children({props})}
            <TextLink {...props} href={href} target="_blank" title={title}>
                {@render linkContent()}
            </TextLink>
        {/snippet}
    </UrlPreviewTooltip>
{:else if kind === 'hash'}
    <TextLink href={href} title={title} onclick={(event) => scrollToHashTarget(event)}>
        {@render linkContent()}
    </TextLink>
{:else if kind === 'unsafe'}
    <span title={title}>{@render linkContent()}</span>
{:else}
    <TextLink href={href} title={title}>
        {@render linkContent()}
    </TextLink>
{/if}
