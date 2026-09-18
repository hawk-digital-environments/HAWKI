<!--
@component Centered empty-state hero shown while a chat has no messages yet.
Rendered by the new-chat page (`ChatIndex.svelte`) and by an opened
conversation without messages (`ChatConversation.svelte`). Reserves space for
the floating composer via the inherited `--composer-dock-height` property set
on the page element.
-->
<script lang="ts">
    import AiChat01Icon from '$lib/components/ui/icons/iconset/AiChat01Icon.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
        /** Heading level of the title: h1 on the new-chat page, h2 inside a conversation that already has an h1. */
        headingLevel?: 1 | 2;
    }

    const {headingLevel = 1}: Props = $props();
    const {__} = useTranslator();
</script>

<div class="welcome">
    <span class="welcome-icon" aria-hidden="true"><AiChat01Icon size={28} /></span>
    <svelte:element this={`h${headingLevel}`} class="title">{__('chat.page.welcomeTitle')}</svelte:element>
    <p>{__('chat.page.welcomeDescription')}</p>
</div>

<style>
    .welcome {
        display: flex;
        height: 100%;
        min-height: 18rem;
        align-items: center;
        justify-content: center;
        padding: var(--space-6);
        padding-bottom: calc(var(--composer-dock-height, 0px) + var(--space-6));
        flex-direction: column;
        text-align: center;
    }

    .welcome-icon {
        display: grid;
        width: 3.25rem;
        height: 3.25rem;
        margin-bottom: var(--space-4);
        place-items: center;
        border-radius: var(--corner-lg);
        background: var(--color-active-surface);
        color: var(--color-active-text);
    }

    .title { margin: 0 0 var(--space-2); font-size: var(--font-size-xl); font-weight: var(--font-weight-medium); }
    p { max-width: 34rem; margin: 0; color: var(--color-text-muted); }
</style>
