<!--
  @component Compact metrics line below an assistant message for the
  `statsForNerds` experiment: output/input tokens, tokens per second, time to
  first token and total duration. Token counts come from the provider via the
  stream's completion packet; timing is measured client-side. Values that are
  not known (yet) render as "n/a" — e.g. tokens while the answer still streams.

  @example
  {#if experiments.isEnabled('statsForNerds') && message.stats}
      <MessageStats stats={message.stats} />
  {/if}
-->
<script lang="ts">
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {MessageStats} from '$plugins/core/modules/chat/types.js';

    interface Props {
        stats: MessageStats;
    }

    const {stats}: Props = $props();
    const {__} = useTranslator();

    const unknown = $derived(__('chat.page.stats.unknown'));

    function integer(value: number | null): string {
        return value === null ? unknown : value.toLocaleString();
    }

    function seconds(value: number | null): string {
        return value === null ? unknown : `${(value / 1000).toFixed(2)} s`;
    }

    const items = $derived([
        {key: 'outputTokens', label: __('chat.page.stats.outputTokens'), value: integer(stats.outputTokens)},
        {key: 'promptTokens', label: __('chat.page.stats.promptTokens'), value: integer(stats.promptTokens)},
        {key: 'tokensPerSecond', label: __('chat.page.stats.tokensPerSecond'), value: stats.tokensPerSecond === null ? unknown : `${stats.tokensPerSecond.toFixed(1)} tok/s`},
        {key: 'timeToFirstToken', label: __('chat.page.stats.timeToFirstToken'), value: seconds(stats.timeToFirstTokenMs)},
        {key: 'duration', label: __('chat.page.stats.duration'), value: seconds(stats.durationMs)},
        {key: 'characters', label: __('chat.page.stats.characters'), value: integer(stats.characters)},
        {key: 'chunks', label: __('chat.page.stats.chunks'), value: integer(stats.chunks)}
    ]);
</script>

<dl class="message-stats" aria-label={__('chat.page.stats.label')}>
    {#each items as item (item.key)}
        <div class="stat">
            <dt>{item.label}</dt>
            <dd>{item.value}</dd>
        </div>
    {/each}
</dl>

<style>
    .message-stats {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-1) var(--space-3);
        margin: var(--space-2) 0 0;
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
        line-height: var(--line-height-normal);
    }

    .stat {
        display: flex;
        gap: var(--space-1);
        white-space: nowrap;
    }

    dt::after { content: ':'; }

    dd {
        margin: 0;
        color: var(--color-text);
        font-variant-numeric: tabular-nums;
    }
</style>
