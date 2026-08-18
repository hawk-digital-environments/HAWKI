<!--
  @component Root of the `citations` component family. Wrap the rendered
  message (markdown body + optional source list) in a `CitationRoot` so that
  inline citation chips (`CitationReference`, rendered by `ExtendedLinkNode`
  as part of the markdown) and their matching source tiles (`Citation`,
  listed inside `CitationList`) can find each other, even though they live
  in different, unrelated parts of the tree.

  It does this by creating one `CitationContext` per instance and providing
  it to descendants — nothing more. It renders no markup of its own.

  Composition (see `MessageBody.svelte` for the real usage):

  ```svelte
  <CitationRoot>
      <Markdown message={messageWithInlineMarkers} />

      {#if citations.length > 0}
          <CitationList>
              {#each citations as citation, index (citation.identifier)}
                  <Citation citation={citation} number={index + 1} />
              {/each}
          </CitationList>
      {/if}
  </CitationRoot>
  ```

  Each citation needs a stable `identifier` shared between its inline marker
  and its tile — see `EnrichedUrlCitation` in `types.ts` and
  `injectCitationsIntoMarkdown`, which inserts the inline `[N](#citation-…)`
  markers that `ExtendedLinkNode` turns into `CitationReference` chips.
-->
<script lang="ts">
    import {createCitationContext} from './CitationContext.js';
    import type {Snippet} from 'svelte';

    interface Props {
        /** The message content (markdown) and, if any citations exist, the `CitationList` of source tiles. */
        children: Snippet;
    }

    const {children}: Props = $props();

    createCitationContext();
</script>

{@render children?.()}
