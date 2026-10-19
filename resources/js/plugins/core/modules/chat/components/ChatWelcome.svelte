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
    import BubbleChatAddIcon from '$lib/components/ui/icons/iconset/BubbleChatAddIcon.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {ComposerContext} from '$plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
    import type {ChatConversation} from '$plugins/core/modules/chat/types.js';
    import type {ChatWelcomeSection} from '$plugins/core/modules/chat/hooks/chatWelcomeHooks.js';
    import {textRise} from '$lib/utils/transitions/textRise.js';

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

    /**
     * What the hero shows, as one value per participant. Rendered through a one-item keyed
     * `{#each}` rather than `{#key}`: an outgoing each-item keeps the value it was rendered
     * with, so the old hero fades out with its own copy instead of the new one.
     */
    const hero = $derived({
        key: section ? `${section.id}:${section.handle ?? section.title}` : 'default',
        icon: section?.icon,
        tint: section?.tint,
        title: section ? section.title : __('chat.page.welcomeTitle'),
        description: section ? (section.description ?? '') : __('chat.page.welcomeDescription'),
        starterPrompts: section?.starterPrompts ?? []
    });
    const leave = {direction: 'out'} as const;

    function selectPrompt(prompt: string) {
        if (!composer) return;
        composer.message = prompt;
        if (section?.handle) {
            composer.addHandleToMessage(section.handle);
        }
        composer.focusInput();
    }
</script>

<!-- Keyed on the addressed participant: tagging or swapping an assistant swaps the whole
     hero, the old copy fading out quietly while the new one rises in line by line. Both
     share one grid cell during the hand-off so the layout doesn't jump. On first render
     the hero appears without motion (local transitions skip the initial mount). -->
<div class="welcome-stage">
    {#each [hero] as current (current.key)}
        <div class="welcome">
            <span
                class="welcome-icon"
                class:personalized={current.icon}
                style:--assistant-tint={current.tint}
                aria-hidden="true"
                in:textRise={{index: 0}}
                out:textRise={leave}>
                {#if current.icon}
                    <span class="welcome-glyph">{current.icon}</span>
                {:else}
                    <BubbleChatAddIcon size={28} />
                {/if}
            </span>
            <svelte:element
                this={`h${headingLevel}`}
                class="title"
                in:textRise={{index: 1}}
                out:textRise={leave}>{current.title}</svelte:element>
            <p class:empty={!current.description} in:textRise={{index: 2}} out:textRise={leave}>{current.description}</p>
            <ul
                class="starter-prompts"
                class:empty={current.starterPrompts.length === 0}
                aria-label={__('chat.page.starterPrompts')}
                in:textRise={{index: 3}}
                out:textRise={leave}>
                {#each current.starterPrompts as prompt}
                    <li>
                        <button type="button" class="starter-prompt" onclick={() => selectPrompt(prompt)}>{prompt}</button>
                    </li>
                {/each}
            </ul>
        </div>
    {/each}
</div>

<style>
    .welcome-stage {
        display: grid;
        height: 100%;
    }

    .welcome {
        grid-area: 1 / 1;
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
        margin-bottom: var(--space-5);
        place-items: center;
        border-radius: var(--corner-full);
        background: var(--color-active-surface);
        color: var(--color-active-text);
    }

    /* A section that brings its own glyph paints the circle in its tint —
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

    .welcome .empty { display: none; }

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
