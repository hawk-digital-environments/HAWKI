<!--
  @component A single source tile for a chat message citation, shown inside a
  `CitationList` grid below the message. Displays the source number, favicon,
  domain and title, and links to the source in a new tab.

  Must be rendered inside a `CitationRoot` — it calls `useCitationContext()`
  directly (no try/catch), so it throws if no ancestor `CitationRoot` created
  one. On mount it registers a listener on that shared context for its own
  `citation.identifier`: when a matching `CitationReference` chip (rendered
  inline in the message markdown) is clicked, this tile scrolls itself into
  view and toggles the `citation-flash` class to play a short highlight
  animation.

  Usage (see `MessageBody.svelte`):

  ```svelte
  <CitationRoot>
      <Markdown message={messageWithInlineMarkers} />
      <CitationList>
          {#each citations as citation, index (citation.identifier)}
              <Citation citation={citation} number={index + 1} />
          {/each}
      </CitationList>
  </CitationRoot>
  ```
-->
<script lang="ts">
    import type {EnrichedUrlCitation} from './types.js';
    import Link from '../../util/link/Link.svelte';
    import UrlPreviewTooltip from '../tooltip/UrlPreviewTooltip.svelte';
    import {useCitationContext} from './CitationContext.js';
    import {onMount} from 'svelte';

    const citationContext = useCitationContext();

    interface Props {
        /** The citation to display. */
        citation: EnrichedUrlCitation;
        /** The 1-based source number, matching the inline citation chips. */
        number: number;
    }

    const {citation, number}: Props = $props();

    let container: HTMLDivElement | null = $state(null);

    const domain = $derived.by(() => {
        try {
            return new URL(citation.url).hostname.replace(/^www\./, '');
        } catch {
            return citation.url;
        }
    });

    onMount(() => {
        return citationContext.onFocusCitation(citation.identifier, () => {
            container?.scrollIntoView({behavior: 'smooth', block: 'center'});
            // Restart the flash animation if it is already running
            container?.classList.remove('citation-flash');
            void container?.offsetWidth;
            container?.classList.add('citation-flash');
            container?.addEventListener(
                'animationend',
                () => container?.classList.remove('citation-flash'),
                {once: true}
            );
        });
    });
</script>

<div bind:this={container} class="citation-tile">
    <UrlPreviewTooltip url={citation.url}>
        {#snippet children({props})}
            <Link {...props} href={citation.url} target="_blank" title={citation.url}>
                {#snippet children({favicon})}
                    <span class="citation-tile__header">
                        <span class="citation-tile__number">{number}</span>
                        {@render favicon()}
                        <span class="citation-tile__domain">{domain}</span>
                    </span>
                {/snippet}
            </Link>
        {/snippet}
    </UrlPreviewTooltip>
</div>

<style>
    .citation-tile {
        --citation-tile-bg: var(--color-surface);
        --citation-tile-title: var(--color-text);

        border-radius: var(--corner-xs);
    }

    .citation-tile:hover,
    .citation-tile:focus-within {
        --citation-tile-bg: var(--color-hover);
    }

    .citation-tile:global(.citation-flash) {
        animation: citation-flash 3s var(--easing-default, ease);
    }

    @keyframes citation-flash {
        0%,
        40% {
            outline: 2px solid var(--color-info);
            box-shadow: 0 0 10px var(--color-info);
        }
        100% {
            outline: 0 solid var(transparent);
            box-shadow: 0 0 10px var(transparent);
        }
    }

    .citation-tile > :global(a) {
        --favicon-size: 16px;
        --favicon-gap: 0;

        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        height: 100%;
        border-radius: var(--corner-xs);
        padding: var(--space-3);
        background: var(--citation-tile-bg);
        text-decoration: none;
        color: inherit;
    }

    .citation-tile__header {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        min-width: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }

    .citation-tile__domain {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .citation-tile__number {
        flex-shrink: 0;
        min-width: 1.6em;
        border-radius: var(--corner-sm);
        background: var(--color-highlight);
        text-align: center;
        line-height: 1.5;
        font-weight: var(--font-weight-medium);
    }
</style>
