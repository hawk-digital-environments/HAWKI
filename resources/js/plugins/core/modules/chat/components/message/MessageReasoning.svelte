<!--
  @component Card showing the model's reasoning ("thinking") as a sequence of
  steps: blocks of thinking text and the web searches it performed in between.

  While the model is still thinking (`active`) the card is expanded and its
  header shows the title of the current thinking step; once the answer starts
  it collapses. The user's last open/closed choice is remembered in localStorage
  and used as the default for finished messages.

  @example
  <MessageReasoning parts={message.reasoning} active={message.isStreaming && !message.content.text} />
-->
<script lang="ts">
    import ArrowDown01Icon from '$lib/components/ui/icons/iconset/ArrowDown01Icon.svelte';
    import AiBrain01Icon from '$lib/components/ui/icons/iconset/AiBrain01Icon.svelte';
    import GlobalSearchIcon from '$lib/components/ui/icons/iconset/GlobalSearchIcon.svelte';
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
    import Link from '$lib/components/util/link/Link.svelte';
    import UrlPreviewTooltip from '$lib/components/ui/tooltip/UrlPreviewTooltip.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import type {ReasoningPart} from '$plugins/core/modules/chat/types.js';

    const STORAGE_KEY = 'hawkiReasoningOpen';

    interface Props {
        /** The reasoning steps in the order they happened. */
        parts: ReasoningPart[];
        /** True while the model is still thinking and has not produced an answer yet. */
        active?: boolean;
    }

    const {parts, active = false}: Props = $props();
    const {__} = useTranslator();
    const app = useApp();

    let userToggled = $state<boolean | null>(null);
    const rememberedOpen = app.localStorage.getItem(STORAGE_KEY) === '1';
    const open = $derived(userToggled ?? (active || rememberedOpen));

    function toggle() {
        userToggled = !open;
        app.localStorage.setItem(STORAGE_KEY, userToggled ? '1' : '0');
    }

    /** Title of the current thinking step (OpenAI streams parts as "**Title**\n\nBody"). */
    const currentTitle = $derived.by(() => {
        const last = parts.at(-1);
        if (last?.type === 'web_search') return __('chat.page.webSearch');
        const matches = last?.text.match(/\*\*([^*\n]+)\*\*/g);
        return matches?.length ? matches[matches.length - 1].slice(2, -2).trim() : null;
    });
    const label = $derived(active ? (currentTitle ?? __('chat.page.thinking')) : __('chat.page.reasoning'));
    const MAX_SOURCES = 6;
    const searchCount = $derived(parts.filter(part => part.type === 'web_search').length);
    const panelId = $props.id();

    function actionLabel(action: string): string {
        if (action === 'open_page') return __('chat.page.webSearchOpened');
        if (action === 'find_in_page') return __('chat.page.webSearchFind');
        return __('chat.page.webSearch');
    }

    function domain(url: string): string {
        try {
            return new URL(url).hostname.replace(/^www\./, '');
        } catch {
            return url;
        }
    }

    /** One chip per site: the first URL of each domain, in the order they were found. */
    function uniqueSources(urls: string[]): string[] {
        const seen = new Set<string>();
        return urls.filter(url => {
            const key = domain(url);
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    }
</script>

<section class="reasoning" class:open class:active aria-label={__('chat.page.reasoning')}>
    <button type="button" class="header" aria-expanded={open} aria-controls={panelId} onclick={toggle}>
        <span class="brain" aria-hidden="true"><AiBrain01Icon size={16} /></span>
        <span class="label">{label}</span>
        {#if !active && searchCount > 0}
            <span class="count"><GlobalSearchIcon size={12} /> {searchCount}</span>
        {/if}
        <span class="chevron" aria-hidden="true"><ArrowDown01Icon size={16} /></span>
    </button>

    {#if open}
        <ol class="steps" id={panelId}>
            {#each parts as part, index (index)}
                {#if part.type === 'web_search'}
                    {@const sources = uniqueSources(part.sources)}
                    <li class="step search">
                        <span class="marker search-marker" aria-hidden="true"><GlobalSearchIcon size={12} /></span>
                        <div class="search-body">
                            <span class="eyebrow">{actionLabel(part.action)}</span>
                            {#if sources.length}
                                <ul class="sources">
                                    {#each sources.slice(0, MAX_SOURCES) as url (url)}
                                        <li>
                                            <UrlPreviewTooltip {url}>
                                                {#snippet children({props})}
                                                    <Link {...props} href={url} target="_blank" title={url} class="source">
                                                        {#snippet children({favicon})}
                                                            {@render favicon()}
                                                            <span class="source-domain">{domain(url)}</span>
                                                        {/snippet}
                                                    </Link>
                                                {/snippet}
                                            </UrlPreviewTooltip>
                                        </li>
                                    {/each}
                                    {#if sources.length > MAX_SOURCES}
                                        <li class="more">{__('chat.page.webSearchMore', {count: String(sources.length - MAX_SOURCES)})}</li>
                                    {/if}
                                </ul>
                            {/if}
                            {#if part.query}
                                <span class="query">„{part.query}“</span>
                            {/if}
                        </div>
                    </li>
                {:else}
                    <li class="step text">
                        <span class="marker" aria-hidden="true"></span>
                        <div class="prose"><Markdown message={part.text} /></div>
                    </li>
                {/if}
            {/each}
        </ol>
    {/if}
</section>

<style>
    .reasoning {
        --rail: var(--color-border);
        margin-block: var(--space-1) var(--space-2);
        border: var(--border);
        border-radius: var(--corner-sm);
        background: var(--color-surface);
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        overflow: hidden;
    }

    .header {
        display: flex;
        align-items: center;
        gap: var(--space-1_5);
        width: 100%;
        padding: var(--space-1_5) var(--space-2);
        background: none;
        border: none;
        color: inherit;
        font: inherit;
        text-align: left;
        cursor: pointer;
    }

    .header:hover { background: var(--color-surface-light); }
    .header:focus-visible { outline: 2px solid var(--color-focus-ring); outline-offset: -2px; }

    .brain { display: inline-flex; color: var(--color-accent-text); }

    .label {
        flex: 1;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 500;
        color: var(--color-text);
    }

    .count {
        display: inline-flex;
        align-items: center;
        gap: var(--space-0_5);
        font-size: var(--font-size-xxs);
        font-variant-numeric: tabular-nums;
    }

    .chevron {
        display: inline-flex;
        transition: transform var(--duration-fast);
    }

    .open .chevron { transform: rotate(180deg); }

    .active .label {
        background: linear-gradient(90deg, var(--color-text-muted) 0%, var(--color-text) 50%, var(--color-text-muted) 100%);
        background-size: 200% 100%;
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        animation: shimmer 1.6s linear infinite;
    }

    .steps {
        --marker-size: 8px;
        --marker-col: var(--space-3);
        list-style: none;
        margin: 0;
        padding: 0 var(--space-2) var(--space-2) var(--space-2);
        border-top: var(--border);
    }

    .step {
        position: relative;
        display: grid;
        grid-template-columns: var(--marker-col) minmax(0, 1fr);
        gap: var(--space-1_5);
        padding-block: var(--space-1_5);
    }

    /* Vertical rail connecting the step markers */
    .step::before {
        content: '';
        position: absolute;
        left: calc(var(--marker-col) / 2 - 0.5px);
        top: 0;
        bottom: 0;
        width: 1px;
        background: var(--rail);
    }

    .step:first-child::before { top: calc(var(--space-1_5) + 0.6em); }
    .step:last-child::before { bottom: calc(100% - var(--space-1_5) - 0.6em); }

    .marker {
        position: relative;
        justify-self: center;
        margin-top: calc(0.6em - var(--marker-size) / 2);
        width: var(--marker-size);
        height: var(--marker-size);
        border-radius: 50%;
        background: var(--color-surface);
        border: 1.5px solid var(--color-border-strong, var(--color-text-muted));
    }

    .search-marker {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        margin-top: calc(0.6em - 9px);
        border-color: var(--color-accent-text);
        color: var(--color-accent-text);
    }

    .search-body {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        min-width: 0;
    }

    .sources {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-1);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sources :global(.source) {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        max-width: 100%;
        padding: 2px var(--space-1_5) 2px var(--space-1);
        border: var(--border);
        border-radius: 999px;
        background: var(--color-surface-raised);
        color: var(--color-text);
        font-size: var(--font-size-xxs);
        text-decoration: none;
        line-height: 1.4;
    }

    .sources :global(.source:hover) { border-color: var(--color-border-strong); }

    .source-domain {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .more {
        align-self: center;
        font-size: var(--font-size-xxs);
    }

    .eyebrow {
        font-size: var(--font-size-xxs);
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--color-accent-text);
    }

    .query {
        font-size: var(--font-size-xxs);
        font-style: italic;
        overflow-wrap: anywhere;
    }

    .prose {
        min-width: 0;
        font-size: var(--font-size-xs);
        line-height: 1.5;
        overflow-wrap: anywhere;
    }

    .prose :global(.markstream-svelte),
    .prose :global(.markstream-svelte *) {
        font-size: inherit;
        line-height: inherit;
    }

    .prose :global([data-node-type]) { margin: 0 0 var(--space-2); }
    .prose :global([data-node-type]:last-child) { margin-bottom: 0; }
    .prose :global(strong) { color: var(--color-text); font-weight: 600; }

    @keyframes shimmer {
        from { background-position: 200% 0; }
        to { background-position: -200% 0; }
    }

    @media (prefers-reduced-motion: reduce) {
        .active .label { animation: none; }
        .chevron { transition: none; }
    }
</style>
