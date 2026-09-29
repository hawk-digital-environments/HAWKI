<!--
  @component Status text with a highlight travelling along it while `active`
  — the "still working" treatment of a running step (e.g. "Thinking…"). The
  text stays readable while it runs, rather than the whole label blinking.
  Inactive it renders as plain inherited text. Under reduced motion the text
  is shown in the accent colour instead.

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
    .shimmer {
        --shimmer-base: color-mix(in oklab, var(--color-accent-text) 60%, var(--color-text-muted));
        background: linear-gradient(
            90deg,
            var(--shimmer-base) 0%,
            var(--shimmer-base) 35%,
            var(--color-accent-text) 50%,
            var(--shimmer-base) 65%,
            var(--shimmer-base) 100%
        );
        background-size: 300% 100%;
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        animation: shimmer 1.5s ease-in-out infinite;
    }

    @keyframes shimmer {
        from { background-position: 0% 0; }
        to { background-position: 100% 0; }
    }

    @media (prefers-reduced-motion: reduce) {
        .shimmer {
            animation: none;
            background: none;
            color: var(--color-accent-text);
        }
    }
</style>
