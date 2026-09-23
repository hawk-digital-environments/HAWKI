<!--
  @component Renders an assistant message body with inline citation markers.

  Wraps the message markdown in a `CitationRoot` and, when `citations` are
  provided (and the message is not actively streaming), injects inline
  `[N](#citation-<id>)` markers into the markdown via
  `injectCitationsIntoMarkdown` and renders a `CitationList` of source tiles
  below the body. Each citation URL is mapped to a stable per-component
  identifier so the inline marker and its tile stay in sync across re-renders.
  Citations pointing at the same URL are deduplicated into one tile, with
  their ranges merged so every cited segment keeps its inline marker.

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
        /**
         * Heading level below the message's own heading: markdown headings in
         * the body start here and the "Sources" heading uses it. Defaults to 4
         * (page h1, message history h2, message author h3).
         */
        headingLevel?: number;
    }

    const {
        message: givenMessage,
        citations: givenCitations = [],
        isStreaming = false,
        headingLevel = 4
    }: Props = $props();

    const componentId = $props.id();

    const citations: Array<EnrichedUrlCitation> = $derived.by(() => {
        if (isStreaming || !Array.isArray(givenCitations)) {
            return [];
        }

        // Providers may report the same URL more than once (one entry per
        // cited text segment). One identifier per URL keeps chip and tile in
        // sync, so duplicate entries would collide as `#each` keys — and
        // would render duplicate tiles. Dedupe by URL, merging the ranges so
        // every cited segment still gets its inline marker.
        const byUrl = new Map<string, EnrichedUrlCitation>();

        for (const citation of givenCitations) {
            const existing = byUrl.get(citation.url);

            if (!existing) {
                byUrl.set(citation.url, {
                    ...citation,
                    identifier: componentId + '-' + crypto.randomUUID()
                });
                continue;
            }

            existing.ranges = [...(existing.ranges ?? []), ...(citation.ranges ?? [])];
            if (!existing.title && citation.title) {
                existing.title = citation.title;
            }
        }

        return [...byUrl.values()];
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
        headingBaseLevel={headingLevel}
    />

    {#if citations.length > 0}
        <CitationList {headingLevel}>
            {#each citations as citation, index (citation.identifier)}
                <Citation citation={citation} number={index + 1}/>
            {/each}
        </CitationList>
    {/if}

</CitationRoot>
