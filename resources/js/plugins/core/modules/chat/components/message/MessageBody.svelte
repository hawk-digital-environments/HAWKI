<script lang="ts">
    import type {EnrichedUrlCitation, UrlCitation as MessageCitationType} from '$lib/components/ui/citations/types.js';
    import {injectCitationsIntoMarkdown} from '$lib/components/chat/message/injectCitationsIntoMarkdown.js';
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
    import CitationRoot from '$lib/components/ui/citations/CitationRoot.svelte';
    import CitationList from '$lib/components/ui/citations/CitationList.svelte';
    import Citation from '$lib/components/ui/citations/Citation.svelte';

    interface Props {
        message: string;
        citations?: Array<MessageCitationType>;
        isStreaming?: boolean;
    }

    const {
        message: givenMessage,
        citations: givenCitations = [],
        isStreaming = false
    }: Props = $props();

    const componentId = $props.id();

    const urlHashMap = new Map<string, string>();

    const citations: Array<EnrichedUrlCitation> = $derived.by(() => {
        if (isStreaming || !Array.isArray(givenCitations)) {
            return [];
        }

        return givenCitations.map(citation => {
            if (!urlHashMap.has(citation.url)) {
                urlHashMap.set(citation.url, componentId + '-' + crypto.randomUUID());
            }

            return {
                ...citation,
                identifier: urlHashMap.get(citation.url)!
            };
        });
    });

    const message = $derived.by(() => {
        if (isStreaming || citations.length === 0) {
            return givenMessage;
        }

        return injectCitationsIntoMarkdown(givenMessage, citations);
    });
</script>

<CitationRoot>
    <Markdown
        message={message}
        isStreaming={isStreaming}
    />

    {#if citations.length > 0}
        <CitationList>
            {#each citations as citation, index (citation.identifier)}
                <Citation citation={citation} number={index + 1}/>
            {/each}
        </CitationList>
    {/if}

</CitationRoot>
