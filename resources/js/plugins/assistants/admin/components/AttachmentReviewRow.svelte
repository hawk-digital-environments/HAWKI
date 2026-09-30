<!--
  @component One knowledge-file row in the admin detail page's Knowledge
  card: icon, title + gray meta line (mime · size · upload date), the
  ok/corrupted/inadequate switch, a "view" button (opens the file in
  FilePreviewPanel) and a "download" link.
-->
<script lang="ts">
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Link from '$lib/components/util/link/Link.svelte';
    import File01Icon from '$lib/components/ui/icons/iconset/File01Icon.svelte';
    import Image01Icon from '$lib/components/ui/icons/iconset/Image01Icon.svelte';
    import EyeIcon from '$lib/components/ui/icons/iconset/EyeIcon.svelte';
    import Download01Icon from '$lib/components/ui/icons/iconset/Download01Icon.svelte';
    import ReviewStatusSwitch from '$plugins/assistants/admin/components/ReviewStatusSwitch.svelte';
    import { reviewAssistantAttachment } from '$plugins/assistants/admin/api/assistantReviewClient';
    import type { UploadFile, AttachmentReviewStatus } from '$plugins/assistants/types/UploadFile';

    const {
        assistantId,
        file,
        onView,
        onReviewed
    }: {
        assistantId: string;
        file: UploadFile;
        onView: (file: UploadFile) => void;
        onReviewed: (uuid: string, status: AttachmentReviewStatus) => void;
    } = $props();
    const app = useApp();
    const { __ } = useTranslator();

    let updating = $state(false);

    function formatSize(bytes?: number): string {
        if (bytes === undefined || bytes === null) return '';
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    function formatDate(date?: Date): string {
        if (!date) return '';
        return date.toLocaleDateString(app.localization.locale.lang.replace('_', '-'));
    }

    const meta = $derived([file.mimeType, formatSize(file.size), formatDate(file.date)].filter(Boolean).join(' · '));
    const downloadUrl = $derived(file.identifier ? app.uriBuilder.storageFileUri(file.identifier) : null);

    async function setStatus(status: AttachmentReviewStatus): Promise<void> {
        if (!file.uuid) return;
        updating = true;
        try {
            await reviewAssistantAttachment(assistantId, file.uuid, status);
            onReviewed(file.uuid, status);
        } finally {
            updating = false;
        }
    }
</script>

<div class="attachment-row">
    <div class="icon">
        {#if file.mimeType?.startsWith('image/')}
            <Image01Icon size={20} />
        {:else}
            <File01Icon size={20} />
        {/if}
    </div>
    <div class="title">
        <span class="name">{file.name}</span>
        <span class="meta">{meta}</span>
    </div>
    <ReviewStatusSwitch value={file.reviewStatus} disabled={updating} onchange={setStatus} />
    <Button variant="stroke" size="sm" onclick={() => onView(file)}>
        <EyeIcon size={16} />
        {__('admin.flags.view')}
    </Button>
    {#if downloadUrl}
        <Link href={downloadUrl} download={file.name} class="download-link">
            <Download01Icon size={16} />
            {__('admin.flags.download')}
        </Link>
    {/if}
</div>

<style>
    .attachment-row {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding: var(--space-2) 0;
        border-bottom: var(--border);
    }
    .attachment-row:last-child {
        border-bottom: none;
    }
    .icon {
        flex: 0 0 auto;
        color: var(--color-text-muted);
    }
    .title {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }
    .name {
        font-size: var(--font-size-sm);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .meta {
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
    }
    :global(.download-link) {
        display: inline-flex;
        align-items: center;
        gap: var(--space-1);
        border: var(--border);
        border-radius: var(--corner-md);
        padding: var(--space-1) var(--space-3);
        font-size: var(--font-size-sm);
        white-space: nowrap;
    }
    :global(.download-link:hover) {
        background: var(--color-hover);
    }
</style>
