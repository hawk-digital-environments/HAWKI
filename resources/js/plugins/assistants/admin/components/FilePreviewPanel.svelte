<!--
  @component Right-docked panel previewing one knowledge file: an image
  renders directly, a PDF renders via the browser's native viewer in an
  `<iframe>`, a Word document (.docx) renders via the `docx-preview` library
  already used by the legacy file viewer (lazy-loaded the same way, through
  `window.hawkiDependencyLoader`), and anything else falls back to a
  "download to view" message. No side-panel primitive exists elsewhere in the
  app, so this restyles the generic `Dialog` to dock right instead of
  centering — see `Dialog.svelte`'s `contentProps.class` escape hatch.
-->
<script lang="ts">
    import { useApp } from '$lib/app/hooks/useApp.svelte.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import Dialog from '$lib/components/ui/dialog/Dialog.svelte';
    import type { UploadFile } from '$plugins/assistants/types/UploadFile';

    const {
        file,
        open,
        onOpenChange
    }: {
        file: UploadFile | null;
        open: boolean;
        onOpenChange: (open: boolean) => void;
    } = $props();
    const app = useApp();
    const { __ } = useTranslator();

    let docxContainer = $state<HTMLDivElement>();
    let docxError = $state(false);

    const previewUrl = $derived.by(() => {
        if (!file?.identifier) return null;
        const base = app.uriBuilder.storageFileUri(file.identifier);
        return base ? `${base}?disposition=inline` : null;
    });
    const kind = $derived.by((): 'image' | 'pdf' | 'docx' | 'unsupported' => {
        const mime = file?.mimeType ?? '';
        if (mime.startsWith('image/')) return 'image';
        if (mime === 'application/pdf') return 'pdf';
        if (mime.includes('wordprocessingml')) return 'docx';
        return 'unsupported';
    });

    $effect(() => {
        if (!open || kind !== 'docx' || !previewUrl) return;
        docxError = false;
        const url = previewUrl;
        (async () => {
            try {
                const response = await fetch(url, { credentials: 'include' });
                const blob = await response.blob();
                const docxPreview = await (window as any).hawkiDependencyLoader('docxPreview');
                if (docxContainer) {
                    docxContainer.innerHTML = '';
                    await docxPreview.renderAsync(blob, docxContainer);
                }
            } catch {
                docxError = true;
            }
        })();
    });
</script>

<Dialog
    {open}
    {onOpenChange}
    closable
    title={file?.name ?? ''}
    contentProps={{ class: 'file-preview-panel' }}
>
    {#snippet children()}
        {#if previewUrl}
            {#if kind === 'image'}
                <img src={previewUrl} alt={file?.name ?? ''} />
            {:else if kind === 'pdf'}
                <iframe src={previewUrl} title={file?.name ?? ''}></iframe>
            {:else if kind === 'docx'}
                {#if docxError}
                    <p class="unsupported">{__('admin.flags.preview_unsupported')}</p>
                {:else}
                    <div class="docx-container" bind:this={docxContainer}></div>
                {/if}
            {:else}
                <p class="unsupported">{__('admin.flags.preview_unsupported')}</p>
            {/if}
        {/if}
    {/snippet}
</Dialog>

<style>
    :global(.file-preview-panel) {
        position: fixed !important;
        inset-block: 0 !important;
        inset-inline-start: auto !important;
        inset-inline-end: 0 !important;
        transform: none !important;
        top: 0 !important;
        right: 0 !important;
        left: auto !important;
        height: 100dvh;
        width: min(40rem, 90vw);
        max-width: none;
        max-height: none;
        border-radius: 0;
        display: flex;
        flex-direction: column;
    }
    img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        margin-inline: auto;
    }
    iframe {
        width: 100%;
        height: 100%;
        border: none;
        flex: 1;
    }
    .docx-container {
        overflow: auto;
        height: 100%;
    }
    .unsupported {
        color: var(--color-text-muted);
        text-align: center;
        margin-top: var(--space-6);
    }
</style>
