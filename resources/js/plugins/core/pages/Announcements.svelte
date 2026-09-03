<!--
  @component Page component for the `/announcements` route (route name
  `announcements.index`, see `core.plugin.ts`) — the "Aktuelles" news feed.
  Renders every non-system, non-policy announcement addressed to the current
  user as a card: publish date, title (the content's first heading), the full
  markdown body, and a footer with the audience and how many users have read
  it. Reached via the profile dropdown (`ProfileButton.svelte`).
-->
<script lang="ts">
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {announcementDisplayTitle, parseAnnouncementContent, stripLeadingHeading} from '$lib/app/components/announcements/announcementContent.js';
    import type {Announcement} from '$plugins/core/schemas/resources/announcements.schema.js';
    import type {RouteProps} from '$lib/components/ui/routing/index.js';

    const {}: RouteProps = $props();

    // Policies and system notices (e.g. upload conditions) are acknowledgement
    // flows, not news — they don't belong in the feed.
    const HIDDEN_TYPES = ['system', 'policy'];

    const app = useApp();
    const store = useStore('announcements');
    const {__} = useTranslator();

    const items = $derived(store.announcements.filter(announcement => !HIDDEN_TYPES.includes(announcement.type)));

    const dateFormat = $derived(new Intl.DateTimeFormat(
        app.localization.locale.lang.replace('_', '-'),
        {dateStyle: 'long'}
    ));

    function cardBody(announcement: Announcement): string {
        return stripLeadingHeading(parseAnnouncementContent(announcement.content).body);
    }
</script>

<div class="announcements-page">
    <h1>{__('ui.announcements.pageTitle')}</h1>

    {#if items.length === 0}
        <p class="announcements-empty">{__('ui.announcements.empty')}</p>
    {:else}
        <ul class="announcements-list">
            {#each items as announcement (announcement.id)}
                <li class="announcement-card">
                    {#if announcement.starts_at}
                        <time datetime={announcement.starts_at}>{dateFormat.format(new Date(announcement.starts_at))}</time>
                    {/if}
                    <h2>{announcementDisplayTitle(announcement)}</h2>
                    <div class="announcement-card__content">
                        <Markdown message={cardBody(announcement)}/>
                    </div>
                    <footer>
                        <span>
                            {announcement.is_global
                                ? __('ui.announcements.publishedForAll')
                                : __('ui.announcements.publishedForYou')}
                        </span>
                        <span>{__('ui.announcements.readBy', {count: String(announcement.seen_count)})}</span>
                    </footer>
                </li>
            {/each}
        </ul>
    {/if}
</div>

<style>
    .announcements-page {
        width: min(52rem, 100%);
        margin-inline: auto;
        padding: var(--space-8) var(--space-4);

        h1 {
            margin-bottom: var(--space-6);
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-bold);
        }
    }

    .announcements-empty {
        color: var(--color-text-muted);
        font-size: var(--font-size-sm);
    }

    .announcements-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-6);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .announcement-card {
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
        border: var(--border);
        border-radius: var(--corner-md);
        background: var(--color-surface-raised);
        padding: var(--space-6);

        > time {
            color: var(--color-text-muted);
            font-size: var(--font-size-xs);
        }

        > h2 {
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-bold);
        }

        > footer {
            display: flex;
            justify-content: space-between;
            gap: var(--space-4);
            border-top: var(--border);
            margin-top: var(--space-2);
            padding-top: var(--space-3);
            color: var(--color-text-muted);
            font-size: var(--font-size-xs);
        }
    }
</style>
