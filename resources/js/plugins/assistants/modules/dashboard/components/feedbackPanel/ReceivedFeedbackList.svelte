<!--
  @component Read-only list of the feedback an assistant has received, newest
  first. Rendered on the assistant detail page below the FeedbackPanel; the
  entries are only visible to the assistant's creator.
-->
<script lang="ts">
    import MessageMultiple01Icon from '$lib/components/ui/icons/iconset/MessageMultiple01Icon.svelte';
    import Badge from '$lib/components/ui/badge/Badge.svelte';
    import type { AssistantFeedback } from '$plugins/assistants/types/assistant/AssistantFeedback';

    let { feedback }: { feedback: AssistantFeedback[] } = $props();

    /** Two-letter monogram for the author's avatar tile. */
    function initials(author: string): string {
        return author
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(part => part[0]?.toUpperCase() ?? '')
            .join('');
    }

    const dateFormat = new Intl.DateTimeFormat('de-DE', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
</script>

<section class="section">
    <div class="section-head">
        <h3 class="section-title">Erhaltenes Feedback</h3>
        {#if feedback.length > 0}
            <Badge variant="secondary">{feedback.length}</Badge>
        {/if}
    </div>

    {#if feedback.length === 0}
        <p class="empty">
            <span class="empty-icon"><MessageMultiple01Icon size={18} /></span>
            <span>Noch kein Feedback erhalten.</span>
        </p>
    {:else}
        <ul class="list">
            {#each feedback as entry (entry.id)}
                <li class="entry">
                    <span class="avatar" aria-hidden="true">{initials(entry.author)}</span>
                    <div class="body">
                        <div class="meta">
                            <span class="author">{entry.author}</span>
                            <time class="date" datetime={entry.createdAt}>
                                {dateFormat.format(new Date(entry.createdAt))}
                            </time>
                        </div>
                        <p class="text">{entry.text}</p>
                    </div>
                </li>
            {/each}
        </ul>
    {/if}
</section>

<style>
    .section {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
    }
    .section-head {
        display: flex;
        align-items: center;
        gap: var(--space-2);
    }
    .section-title {
        margin: 0;
        font-size: var(--font-size-lg);
        font-weight: var(--font-weight-medium);
        letter-spacing: -0.01em;
        color: var(--color-text);
    }

    .empty {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        margin: 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
    }
    .empty-icon {
        display: inline-flex;
        align-items: center;
        line-height: 0;
    }

    .list {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .entry {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: var(--space-3);
        padding: var(--space-3);
        background: var(--color-surface-raised);
        border: var(--border);
        border-radius: var(--corner-md);
    }
    .avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-medium);
        color: var(--color-accent-text);
        background: var(--color-accent-100);
        border-radius: var(--corner-full);
        user-select: none;
    }
    .body {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        min-width: 0;
    }
    .meta {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: var(--space-2);
    }
    .author {
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        color: var(--color-text);
    }
    .date {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
    .text {
        margin: 0;
        max-width: 60ch;
        font-size: var(--font-size-sm);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }
</style>
