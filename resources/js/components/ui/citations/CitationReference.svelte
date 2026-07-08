<script lang="ts">
    import type {Snippet} from 'svelte';
    import type {EnrichedUrlCitation} from '$lib/components/ui/citations/types.js';
    import {type CitationContext, useCitationContext} from '$lib/components/ui/citations/CitationContext.js';
    import Link from '$lib/components/util/link/Link.svelte';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {__} from '$lib/utils/translator.js';
    import {citationAnchorId} from '$lib/components/chat/message/injectCitationsIntoMarkdown.js';

    // svelte-ignore non_reactive_update
    let citationContext: CitationContext | null = null;
    try {
        citationContext = useCitationContext();
    } catch {
    }

    const toastContext = useToastContext();

    interface Props {
        citation: string | EnrichedUrlCitation;
        title?: string;
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
