<!--
  @component Status text with a highlight travelling along it while `active`
  — the "still working" treatment of a running step (e.g. "Thinking…"). The
  text stays readable while it runs, rather than the whole label blinking.
  Inactive it renders as plain inherited text. Under reduced motion the text
  is shown in the muted text colour instead.

  @example
  ```svelte
  <ShimmerText active={isStreaming}>{__('chat.page.thinking')}</ShimmerText>
  ```
-->
<script lang="ts">
    import type {Snippet} from 'svelte';

    interface Props {
        /** Whether the highlight runs. */
        active?: boolean;
        children: Snippet;
    }

    const {active = true, children}: Props = $props();
</script>

<span class:shimmer={active}>{@render children()}</span>

<style>
    /* A muted label with a full-contrast glint (about half the text wide)
       travelling left to right. The glint starts and ends off the text, so
       each pass reads as a distinct sweep. Both tones follow the theme. */
    .shimmer {
        --shimmer-base: var(--color-text-muted);
        --shimmer-glint: var(--color-text);
        background: linear-gradient(
            90deg,
            var(--shimmer-base) 0%,
            var(--shimmer-base) 40%,
            var(--shimmer-glint) 50%,
            var(--shimmer-base) 60%,
            var(--shimmer-base) 100%
        );
        background-size: 250% 100%;
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        animation: shimmer 1.8s linear infinite;
    }

    @keyframes shimmer {
        from { background-position: 100% 0; }
        to { background-position: 0% 0; }
    }

    @media (prefers-reduced-motion: reduce) {
        .shimmer {
            animation: none;
            background: none;
            color: var(--shimmer-base);
        }
    }
</style>
