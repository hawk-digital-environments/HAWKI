<!--
  @component Renders an assistant message body with inline citation markers.

  Wraps the message markdown in a `CitationRoot` and, when `citations` are
  provided (and the message is not actively streaming), injects inline
  `[N](#citation-<id>)` markers into the markdown via
  `injectCitationsIntoMarkdown` and renders a `CitationList` of source tiles
  below the body. Each citation URL is mapped to a stable per-component
  identifier so the inline marker and its tile stay in sync across re-renders.

  Use this for assistant messages that may carry URL citations; pass the raw
  markdown plus the server-provided citation list and let it handle the wiring.

  @example
  <MessageBody
      message={assistantMessage.text}
      citations={assistantMessage.citations}
      isStreaming={assistantMessage.isStreaming}
  />
-->
<script lang="ts">
    import type {EnrichedUrlCitation, UrlCitation as MessageCitationType} from '$lib/components/ui/citations/types.js';
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
    import CitationRoot from '$lib/components/ui/citations/CitationRoot.svelte';
    import CitationList from '$lib/components/ui/citations/CitationList.svelte';
    import Citation from '$lib/components/ui/citations/Citation.svelte';
    import {injectCitationsIntoMarkdown} from '$plugins/core/modules/chat/components/message/injectCitationsIntoMarkdown.js';

    interface Props {
        /** The message body as markdown. Citations are injected into this string. */
        message: string;
        /** Server-provided URL citations for this message. Ignored while `isStreaming` is true. */
        citations?: Array<MessageCitationType>;
        /** When true the body is treated as a live stream: no citation injection, no citation list. */
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
