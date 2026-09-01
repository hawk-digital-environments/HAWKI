<!--
  @component App-level dialog for pending announcements. Renders whatever the
  announcement store queues as `current` (unanchored pending announcements on
  page load, anchored ones when a feature calls `triggerAnchor`), one at a
  time, and marks each as seen when it is displayed. Confirming accepts the
  announcement on the server; forced announcements cannot be dismissed and
  declining them logs the user out after a warning (matching the legacy UI).
  Mounted once in `AppLayout.svelte` — no props, reads the `announcements`
  store directly.
-->
<script lang="ts">
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Markdown from '$lib/components/util/markdown/Markdown.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {announcementDisplayTitle, parseAnnouncementContent} from '$lib/app/components/announcements/announcementContent.js';

    const app = useApp();
    const store = useStore('announcements');
    const {__} = useTranslator();

    const current = $derived(store.current);
    const parsed = $derived(current ? parseAnnouncementContent(current.content) : null);
    // Forced announcements offer decline-to-logout instead of a close button;
    // non-forced ones only show a decline button when the content defines one.
    const showDecline = $derived(current !== null && (current.is_forced || parsed?.declineLabel !== null));

    let accepting = $state(false);
    let logoutConfirmOpen = $state(false);

    // Seen is recorded on display, acceptance only on confirm — an announcement
    // that was shown but never confirmed stays pending for the next page load.
    $effect(() => {
        if (current && current.seen_at === null) {
            void store.markSeen(current);
        }
    });

    async function confirm() {
        if (!current || accepting) return;
        accepting = true;
        try {
            await store.accept(current);
        } finally {
            accepting = false;
        }
    }

    function decline() {
        if (!current) return;
        if (current.is_forced) {
            logoutConfirmOpen = true;
        } else {
            store.dismiss(current);
        }
    }

    function handleOpenChange(open: boolean) {
        if (!open && current && !current.is_forced) {
            store.dismiss(current);
        }
    }
</script>

{#if current && parsed}
    <Dialog
        open={true}
        onOpenChange={handleOpenChange}
        closable={!current.is_forced}
        contentProps={{
            'class': 'announcement-dialog-content',
            'aria-label': announcementDisplayTitle(current),
            ...(current.is_forced ? {
                onEscapeKeydown: (e: Event) => e.preventDefault(),
                onInteractOutside: (e: Event) => e.preventDefault()
            } : {})
        }}
    >
        {#snippet children()}
            <div class="announcement-dialog-body">
                <Markdown message={parsed.body}/>
            </div>
        {/snippet}
        {#snippet footer()}
            {#if showDecline}
                <Button variant="stroke" size="sm" disabled={accepting} onclick={decline}>
                    {parsed.declineLabel ?? __('ui.announcements.decline')}
                </Button>
            {/if}
            <Button variant="fill" size="sm" disabled={accepting} onclick={confirm}>
                {parsed.confirmLabel ?? __('ui.announcements.confirm')}
            </Button>
        {/snippet}
    </Dialog>
{/if}

<!-- Mounted lazily so its portal is appended to the body *after* the
     announcement dialog's — both sit on --layer-overlay, so DOM order decides
     which one paints on top. -->
{#if logoutConfirmOpen}
    <ConfirmDialog
        bind:open={logoutConfirmOpen}
        title={__('ui.announcements.logoutWarningTitle')}
        description={__('ui.announcements.logoutWarningDescription')}
        confirmVariant="delete"
        onConfirm={() => app.logout()}
    />
{/if}

<style>
    :global(.announcement-dialog-content.announcement-dialog-content) {
        max-width: 40rem;
    }

    .announcement-dialog-body {
        overflow-y: auto;
        max-height: min(60vh, 32rem);
        overscroll-behavior: contain;
    }
</style>
