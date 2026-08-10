<!--
  @component The small inline chip a citation renders as inside the message
  text (e.g. "[1]"). Rendered by `ExtendedLinkNode` for links whose href
  starts with the citation anchor prefix — normal consumers don't place this
  directly, it comes from `injectCitationsIntoMarkdown` markers inside the
  markdown body:

  ```svelte
  <CitationReference citation={citationIdFromAnchorId(href) ?? ''} title={title}>
      {@render linkContent()}
  </CitationReference>
  ```

  Clicking the chip asks the shared `CitationContext` (provided by an
  ancestor `CitationRoot`) to scroll to and flash the matching `Citation`
  tile in the `CitationList`, using the `identifier` both share. If no
  `CitationRoot` is present in the tree — e.g. the message is rendered
  without a citations root — the click shows an error toast instead of
  throwing, since this component may be reused in a context where citations
  aren't wired up.
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {EnrichedUrlCitation} from '$lib/components/ui/citations/types.js';
    import {type CitationContext, useCitationContext} from '$lib/components/ui/citations/CitationContext.js';
    import Link from '$lib/components/util/link/Link.svelte';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {citationAnchorId} from '$plugins/core/modules/chat/components/message/injectCitationsIntoMarkdown.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    // svelte-ignore non_reactive_update
    let citationContext: CitationContext | null = null;
    try {
        citationContext = useCitationContext();
    } catch {
    }

    const toastContext = useToastContext();
    const {__} = useTranslator();

    interface Props {
        /** The cited identifier, or the full `EnrichedUrlCitation` (only `.identifier` is used) — whichever the caller has on hand. */
        citation: string | EnrichedUrlCitation;
        /** Native `title` attribute shown on hover, typically the source URL. */
        title?: string;
        /** The chip's visible content, e.g. the citation number "1". */
        children: Snippet;
    }

    const {citation, title, children}: Props = $props();

    const identifier = $derived.by(() => {
        if (typeof citation === 'string') {
            return citation;
        }
        return citation.identifier;
    });

    function handleClick(event: MouseEvent) {
        event.preventDefault();
        if (!citationContext) {
            toastContext.error(__('chat.message.citationReference.contextError'));
            return;
        }
        if (citationContext) {
            citationContext.focusCitation(identifier);
        }
    }
</script>

<Link class="citation-reference" href={citationAnchorId(identifier)} title={title} onclick={handleClick}>
    {@render children?.()}
</Link>

<style>
    :global(a.citation-reference) {
        text-decoration: none;
        color: inherit;
        cursor: pointer;
        display: inline-block;
        padding: 0 0.35rem;
        border-radius: var(--border-radius-tight);
        background-color: var(--color-surface-light);
        margin-left: 0.2rem;
        font-size: var(--font-size-xs);
        font-weight: 200;

        &:hover {
            background-color: var(--color-hover);
        }
    }
</style>
