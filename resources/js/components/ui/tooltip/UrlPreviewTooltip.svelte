<!--
  @component Tooltip that shows a rich preview (image, title, description,
  favicon + domain) for an external URL. Metadata is fetched lazily through
  the backend link-preview proxy the first time the tooltip opens and cached
  for the lifetime of the page. Shows a spinner while loading and a fallback
  message when the preview cannot be fetched.

    <UrlPreviewTooltip url="https://example.com">
        {#snippet children({props})}
            <a {...props} href="https://example.com">example.com</a>
        {/snippet}
    </UrlPreviewTooltip>
-->
<script lang="ts">
    import type {Snippet} from 'svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import {fetchLinkPreviewMetadata} from '$lib/data/api/linkPreview.js';
    import {__} from '$lib/utils/translator.js';

    interface Props {
        /** The URL to fetch and display the preview for. */
        url: string;
        /**
         * The trigger content. Receives a `props` object that MUST be spread
         * onto the root element of the snippet for the tooltip to work.
         */
        children: Snippet<[{ props: Record<string, any> }]>;
    }

    const {url, children: trigger}: Props = $props();

    let disabled = $state<boolean>(false);

    async function extendedFetchLinkPreviewMetadata(url: string) {
        try {
            return await fetchLinkPreviewMetadata(url);
        } catch (error) {
            // Don't let the tooltip open again if the fetch failed, to avoid repeated failed requests.
            disabled = true;
            throw error;
        }
    }

</script>

{#snippet tooltipBody()}
    <div class="url-preview">
        {#await extendedFetchLinkPreviewMetadata(url)}
            <div class="url-preview__loading">
                <span class="url-preview__spinner" aria-hidden="true"></span>
                <span>{__('Preview_Loading')}</span>
            </div>
        {:then metadata}
            <div class="url-preview__content">
                {#if metadata.image}
                    <div class="url-preview__image-container" class:is-fallback={metadata.image.includes('url=fallback_')}>
                        <img class="url-preview__image" src={metadata.image} alt="" loading="lazy"/>
                    </div>
                {/if}
                <div class="url-preview__details">
                    {#if metadata.title}
                        <div class="url-preview__title">{metadata.title}</div>
                    {/if}
                    {#if metadata.description}
                        <div class="url-preview__description">{metadata.description}</div>
                    {/if}
                    <div class="url-preview__footer">
                        {#if metadata.favicon}
                            <img class="url-preview__favicon" src={metadata.favicon} alt="" loading="lazy"/>
                        {/if}
                        <span class="url-preview__domain">{metadata.domain ?? url}</span>
                    </div>
                </div>
            </div>
        {:catch}
            <div class="url-preview__error">
                <span>{__('Preview_Unavailable')}</span>
                <span class="url-preview__domain">{url}</span>
            </div>
        {/await}
    </div>
{/snippet}

<Tooltip {disabled} tooltip={tooltipBody} class="url-preview-tooltip" delayDuration={500}>
    {#snippet children(args)}
        {@render trigger?.(args)}
    {/snippet}
</Tooltip>

<style>
    :global(.tooltip-content.url-preview-tooltip) {
        padding: 0;
        max-width: min(400px, 90vw);
        overflow: hidden;
        border-radius: var(--corner-md);
    }

    .url-preview {
        width: 400px;
        max-width: 90vw;
    }

    .url-preview__loading {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-12) var(--space-8);
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    .url-preview__spinner {
        width: var(--space-6);
        height: var(--space-6);
        border: 2px solid var(--color-border);
        border-top-color: var(--color-interactive);
        border-radius: var(--corner-full);
        animation: url-preview-spin 0.8s linear infinite;
    }

    @keyframes url-preview-spin {
        to {
            rotate: 360deg;
        }
    }

    .url-preview__content {
        display: flex;
        flex-direction: column;
        max-height: 500px;
    }

    .url-preview__image-container {
        height: 150px;
        overflow: hidden;
        background: var(--color-surface);

        &.is-fallback {
            height: 50px;
        }
    }

    .url-preview__image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .url-preview__details {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        padding: var(--space-4);
    }

    .url-preview__title {
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        line-height: var(--line-height-normal);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .url-preview__description {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        line-height: var(--line-height-normal);
        display: -webkit-box;
        -webkit-line-clamp: 3;
        line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .url-preview__footer {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        font-size: var(--font-size-xxs);
        color: var(--color-text-muted);
        min-width: 0;
    }

    .url-preview__favicon {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }

    .url-preview__domain {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        width: 100%;
    }

    .url-preview__error {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-6) var(--space-4);
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
        text-align: center;
    }
</style>
