<script lang="ts">
    import SquareLock02Icon from '$lib/components/ui/icons/iconset/SquareLock02Icon.svelte';
    import SentIcon from '$lib/components/ui/icons/iconset/SentIcon.svelte';
    import Button from "$lib/components/ui/button/Button.svelte";
    import type {Assistant} from "$lib/plugins/assistants/types/assistant/Assistant";
    import Textarea from "$lib/components/ui/textarea/Textarea.svelte";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";

    const {__} = useTranslator();

    let {
        assistant,
        onsend
    } = $props <{
        assistant: Assistant;
        onsend?: (feedback: string) => void;
    }>();

    let feedbackContent = $state('');
    // Bumped after a send so the uncontrolled Textarea remounts empty.
    let inputGeneration = $state(0);

    function onSubmit() {
        const value = feedbackContent.trim();
        if (value === '') {
            return;
        }
        // Sending is a visual shell for now — hand off to the caller if wired.
        onsend?.(value);
        feedbackContent = '';
        inputGeneration += 1;
    }
</script>

<section class="feedback">
    <div class="feedback-head">
        <h3 class="feedback-title">{__("assistants.feedback.feedback_to")} {assistant.creator.displayName}</h3>
        <p class="feedback-hint">
            <span class="icon"><SquareLock02Icon size="1em" /></span>
            <span>{__("assistants.feedback.visible_to_creator")}</span>
        </p>
    </div>

    {#key inputGeneration}
        <Textarea
                id="feedback-input"
                placeholder="Was gefällt dir? Was könnte verbessert werden?"
                oninput={(e) => { feedbackContent = e.currentTarget.value; }}
        />
    {/key}

    <div class="feedback-actions">
        <Button
            size="md"
            iconLeft={SentIcon}
            variant="fill"
            onclick={onSubmit}
            disabled={!feedbackContent}
        >{__("assistants.feedback.send_feedback")}</Button>
    </div>
</section>

<style>
    .feedback {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
    }
    .feedback-head {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }
    .feedback-title {
        margin: 0;
        font-size: var(--font-size-lg);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.01em;
        color: var(--color-text);
    }
    .feedback-hint {
        display: flex;
        align-items: center;
        gap: var(--space-1_5);
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
    .feedback-hint .icon {
        display: inline-flex;
        align-items: center;
        font-size: var(--font-size-sm);
        line-height: 0;
    }
    .feedback-actions {
        display: flex;
        align-items: center;
    }
</style>
