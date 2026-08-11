<!--
  @component Renders the "Sources" section below an AI message: a heading
  followed by a responsive grid of source tiles. Must be rendered inside a
  `CitationRoot` (a `Citation` tile placed inside `children` looks up the
  shared `CitationContext` on mount and throws otherwise), and only when
  there is at least one citation to show — this component renders its
  heading unconditionally, it does not hide itself when `children` is empty.

  Usage (see `MessageBody.svelte`):

  ```svelte
  <CitationRoot>
      <Markdown message={message} />
      {#if citations.length > 0}
          <CitationList>
              {#each citations as citation, index (citation.identifier)}
                  <Citation citation={citation} number={index + 1} />
              {/each}
          </CitationList>
      {/if}
  </CitationRoot>
  ```
-->
<script lang="ts">

    import type {Snippet} from 'svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    const {__} = useTranslator();

    interface Props {
        /** One or more `Citation` tiles to lay out in the grid. */
        children: Snippet;
    }

    const {children}: Props = $props();
</script>
<section class="message-citations">
    <h3 class="message-citations__heading">{__('chat.message.sources')}</h3>
    <div class="message-citations__grid">
        {@render children?.()}
    </div>
</section>

<style>
    .message-citations {
        margin-block-start: var(--space-6);
    }

    .message-citations__heading {
        margin-block: 0 var(--space-3);
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        color: var(--color-text-muted);
    }

    .message-citations__grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: var(--space-2);
    }

    @media (--bp-md-and-smaller) {
        .message-citations__grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (--bp-xs) {
        .message-citations__grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
