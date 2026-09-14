<!--
@component Centered empty-state hero shown while a chat has no messages yet.
Rendered by the new-chat page (`ChatIndex.svelte`) and by an opened
conversation without messages (`ChatConversation.svelte`). Reserves space for
the floating composer via the inherited `--composer-dock-height` property set
on the page element.

Applies the `chatWelcome` hook: plugins may replace the default hero with a
section describing the currently addressed participant — e.g. the assistants
plugin shows the addressed assistant's name, greeting and starter prompts.
-->
<script lang="ts">
    import AiChat01Icon from '$lib/components/ui/icons/iconset/AiChat01Icon.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import type {ChatConversation} from '$plugins/core/modules/chat/types.js';
    import type {ChatWelcomeSection} from '$plugins/core/modules/chat/hooks/chatWelcomeHooks.js';

    interface Props {
        /** The page's composer; starter prompts pre-fill it. May be null until ready. */
        composer?: ComposerContext | null;
        /** The opened conversation, when the welcome shows inside one. */
        conversation?: ChatConversation | null;
        /** Heading level of the title: h1 on the new-chat page, h2 inside a conversation that already has an h1. */
        headingLevel?: 1 | 2;
    }

    const {composer = null, conversation = null, headingLevel = 1}: Props = $props();
    const app = useApp();
    const {__} = useTranslator();

    /** The hook section with `starterPrompts` normalised to a list. */
    type ResolvedWelcomeSection = ChatWelcomeSection & {starterPrompts: string[]};

    const section = $derived.by((): ResolvedWelcomeSection | null => {
        const resolved: ChatWelcomeSection | null = app.hooks.apply('chatWelcome', null, {
            composer,
            conversation
        });

        if (!resolved) {
            return null;
        }

        return {...resolved, starterPrompts: resolved.starterPrompts ?? []};
    });

    function selectPrompt(prompt: string) {
        if (!composer) return;
        composer.message = prompt;
        if (section?.handle) {
            composer.addHandleToMessage(section.handle);
        }
        composer.focusInput();
    }
</script>

{#if section}
    <div class="welcome">
        <span class="welcome-icon" class:personalized={section.icon} style:--assistant-tint={section.tint} aria-hidden="true">
            {#if section.icon}
                <span class="welcome-glyph">{section.icon}</span>
            {:else}
                <AiChat01Icon size={28} />
            {/if}
        </span>
        <svelte:element this={`h${headingLevel}`} class="title">{section.title}</svelte:element>
        {#if section.description}
            <p>{section.description}</p>
        {/if}
        {#if section.starterPrompts.length > 0}
            <ul class="starter-prompts" aria-label={__('chat.page.starterPrompts')}>
                {#each section.starterPrompts as prompt}
                    <li>
                        <button type="button" class="starter-prompt" onclick={() => selectPrompt(prompt)}>{prompt}</button>
                    </li>
                {/each}
            </ul>
        {/if}
    </div>
{:else}
    <div class="welcome">
        <span class="welcome-icon" aria-hidden="true"><AiChat01Icon size={28} /></span>
        <svelte:element this={`h${headingLevel}`} class="title">{__('chat.page.welcomeTitle')}</svelte:element>
        <p>{__('chat.page.welcomeDescription')}</p>
    </div>
{/if}

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

    /* A section that brings its own glyph paints the square in its tint —
       the same treatment an assistant-authored message's circle gets. */
    .welcome-icon.personalized {
        background: color-mix(in oklab, var(--assistant-tint, var(--color-active-surface)) 18%, transparent);
    }

    .welcome-glyph {
        font-size: 1.6rem;
        line-height: 1;
    }

    .title { margin: 0 0 var(--space-2); font-size: var(--font-size-xl); font-weight: var(--font-weight-medium); }
    p { max-width: 34rem; margin: 0; color: var(--color-text-muted); }

    .starter-prompts {
        display: flex;
        max-width: 40rem;
        margin: var(--space-6) 0 0;
        padding: 0;
        flex-wrap: wrap;
        gap: var(--space-2);
        justify-content: center;
        list-style: none;
    }

    .starter-prompt {
        padding: var(--space-2) var(--space-4);
        border: var(--divider);
        border-radius: var(--corner-full);
        background: var(--color-surface-raised);
        color: var(--color-text);
        font-size: var(--font-size-sm);
        cursor: pointer;
        transition: border-color 120ms ease, background-color 120ms ease;
    }

    .starter-prompt:hover { border-color: var(--color-interactive); }
    .starter-prompt:focus-visible { outline: var(--focus-outline); outline-offset: 2px; }
</style>
