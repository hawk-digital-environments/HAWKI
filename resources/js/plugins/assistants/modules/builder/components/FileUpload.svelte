<script lang="ts">
    import FileUploadIcon from '$lib/components/ui/icons/iconset/FileUploadIcon.svelte';
    import File01Icon from '$lib/components/ui/icons/iconset/File01Icon.svelte';
    import DragDropOverlay from '$plugins/assistants/components/dragDropOverlay/DragDropOverlay.svelte';
    import { useBuilderContext } from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import type { UploadFile } from '$plugins/assistants/types/UploadFile';
    import GenericItemList from '$plugins/assistants/components/itemList/GenericItemList.svelte';
    import Item from '$plugins/assistants/components/itemList/Item.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import { dragDrop } from '$plugins/assistants/actions/dragDrop.svelte.js';
    import { useToastContext } from '$lib/components/ui/toast/ToastContext.svelte';
    import { ApiError } from '$plugins/assistants/api/errors';
    import {
        uploadAssistantAttachmentQueue,
        deleteAssistantAttachment
    } from '$plugins/assistants/api/resources/assistantAttachmentClient';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte';
    import { useConfig } from '$lib/app/hooks/useConfig.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import InformationCircleIcon from '$lib/components/ui/icons/iconset/InformationCircleIcon.svelte';
    import RadialProgress from '$lib/components/ui/radial-progress/RadialProgress.svelte';

    const { __ } = useTranslator();
    const toast = useToastContext();
    const builder = useBuilderContext();

    let currentFiles = $derived((builder.draft.files ?? []) as UploadFile[]);

    /**
     * Mean progress (0–100) over the currently uploading/pending batch;
     * `undefined` while no upload is in flight, which restores the icon.
     */
    let uploadProgress = $derived.by(() => {
        const active = currentFiles.filter((f) => f.status === 'uploading' || f.status === 'pending');
        if (active.length === 0) return undefined;
        return Math.round(active.reduce((sum, f) => sum + (f.progress ?? 0), 0) / active.length);
    });

    let fileInput = $state<HTMLInputElement | null>(null);

    /** Guards against duplicate in-flight delete requests (rapid trash-icon clicks). */
    let deleting = $state(false);

    /** Active drag-over state for the drop zone; drives the overlay badge. */
    let dragState = $state<'idle' | 'valid' | 'invalid'>('idle');

    const config = useConfig();

    /** Config-driven upload constraints (`storage_files` is absent while uploads are disabled). */
    const allowedMimeTypes = $derived(config.storage_files?.allowedMimeTypes ?? []);
    const allowedExtensions = $derived(config.storage_files?.allowedExtensions ?? []);
    const maxFileSize = $derived(config.storage_files?.maxFileSize ?? 0);

    /**
     * Accept filter for the native file picker: config MIME types plus
     * dot-prefixed extensions. Kept as one derived so variants (mime-only,
     * extensions-only, none) are easy to A/B while comparing picker
     * performance across browsers — long lists can make the OS dialog slow.
     */
    const acceptFilter = $derived(
        [...allowedMimeTypes, ...allowedExtensions.map((ext) => `.${ext}`)].join(',') || undefined
    );

    /** Human-readable extension list shared by the tooltip and its screen-reader label. */
    const extensionDisplay = $derived(allowedExtensions.map((ext) => `.${ext}`).join(', '));
    const restrictionText = $derived(
        `${__('assistants.builder.knowledge.upload_max_size')}: ${formatSize(maxFileSize)}. ` +
            `${__('assistants.builder.knowledge.upload_allowed_extensions')}: ${extensionDisplay}`
    );

    function formatSize(bytes?: number): string {
        if (bytes === undefined || bytes === null) return '';
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    function formatDate(date?: Date): string {
        if (!date) return '';
        const d = date instanceof Date ? date : new Date(date);
        return d.toLocaleDateString();
    }

    /** Human-readable upload state appended to each file's description. */
    function formatStatus(file: UploadFile): string {
        switch (file.status) {
            case 'pending':
                return __('assistants.builder.knowledge.upload_title');
            case 'uploading':
                return `${file.progress ?? 0}%`;
            case 'error':
                return file.error ?? 'error';
            default:
                return '';
        }
    }

    /** Replace the draft's files array, patching the entry whose local `file`
     *  reference matches. Mirrors the per-file status mutations HAWKI applied
     *  via the `SendMessageStatus` object. */
    function patchFile(fileRef: File | undefined, patch: Partial<UploadFile>): void {
        const next = (builder.draft.files ?? []).map((f) => (f.file === fileRef ? { ...f, ...patch } : f));
        builder.set('files', next);
    }

    /**
     * Client-side type filter applied just before queueing an upload, in place
     * of an `accept` attribute — the config-driven extension list is far too
     * long for the native file picker (it makes the OS dialog take seconds to
     * open). Unconfigured lists, extension-less files and unknown MIME types
     * pass through so the server remains the real enforcer.
     */
    function isFileAccepted(file: File): boolean {
        if (allowedMimeTypes.length === 0 && allowedExtensions.length === 0) return true;
        const dot = file.name.lastIndexOf('.');
        const ext = dot !== -1 ? file.name.slice(dot + 1).toLowerCase() : undefined;
        if (ext === undefined) return true;
        const mime = file.type.toLowerCase();
        return allowedExtensions.includes(ext) || (mime !== '' && allowedMimeTypes.includes(mime));
    }

    // --- File handling ---
    /**
     * Add files to the queue and kick off the upload immediately.
     *
     * Unify with hawki frontend function `uploadAttachmentQueue` when migrating
     * to hawki frontend. Ported from HAWKI/public/js/attachment_handler.js:145 —
     * `status.setFileProgress(...)` / `status.setFileUuid(...)` side-effects are
     * expressed here as per-file `patchFile(...)` updates on the builder store.
     */
    async function addFiles(fileList: FileList): Promise<void> {
        if (uploadProgress !== undefined) return; // upload in flight — dropzone is disabled

        const incoming = Array.from(fileList);
        const accepted = incoming.filter(isFileAccepted);
        for (const rejected of incoming.filter((f) => !isFileAccepted(f))) {
            toast.error(`${rejected.name}: ${__('assistants.builder.knowledge.upload_rejected_type')}`);
        }
        if (accepted.length === 0) return;

        const queued: UploadFile[] = accepted.map((file) => ({
            name: file.name,
            size: file.size,
            mimeType: file.type,
            date: new Date(),
            file,
            status: 'pending',
            progress: 0
        }));
        builder.set('files', [...(builder.draft.files ?? []), ...queued]);

        const assistantId = builder.draft.id;
        if (!assistantId) return;

        queued.forEach((q) => patchFile(q.file, { status: 'uploading', progress: 0 }));

        const results = await uploadAssistantAttachmentQueue(
            assistantId,
            accepted,
            // Progress only — don't flip status to "complete" here: axios fires
            // 100% when the byte stream finishes, which can still yield a 422.
            (file, progress) => patchFile(file, { progress })
        );

        // Reconcile per result: mark successes, drop failures with a toast.
        const current = builder.draft.files ?? [];
        const next: UploadFile[] = [];
        for (const f of current) {
            const idx = queued.findIndex((q) => q.file === f.file);
            if (idx === -1) {
                next.push(f); // not part of this batch — keep untouched
                continue;
            }
            const result = results[idx];
            if (result?.error) {
                const reason =
                    result.error.errors[0]?.detail ?? result.error.fieldErrors[0]?.message ?? result.error.userMessage;
                toast.error(`${f.name}: ${reason}`);
                continue; // drop the failed file from the list
            }
            next.push({
                ...f,
                status: 'complete',
                progress: 100,
                ...(result?.uuid ? { uuid: result.uuid } : {})
            });
        }
        builder.set('files', next);
    }

    /**
     * Remove a file. If it has been persisted (has a uuid), the server copy is
     * deleted first via the attachment delete action; on failure the local
     * entry is kept so the user can retry.
     *
     * Unify with hawki frontend function `requestAtchDelete` when migrating to
     * hawki frontend. Ported from HAWKI/public/js/attachment_handler.js:68.
     */
    async function removeFile(index: number): Promise<void> {
        if (deleting) return;
        const files = builder.draft.files ?? [];
        const target = files[index];
        if (!target) return;

        const assistantId = builder.draft.id;
        if (assistantId && target.uuid) {
            deleting = true;
            try {
                await deleteAssistantAttachment(assistantId, target.uuid);
            } catch (err) {
                const apiErr = ApiError.from(err);
                const reason = apiErr.errors[0]?.detail ?? apiErr.fieldErrors[0]?.message ?? apiErr.userMessage;
                toast.error(`${target.name}: ${reason}`);
                return; // keep the row — surface the failure via toast
            } finally {
                deleting = false;
            }
        }
        builder.set(
            'files',
            files.filter((_, i) => i !== index)
        );
    }

    function browse(): void {
        if (uploadProgress !== undefined) return; // upload in flight — dropzone is disabled
        fileInput?.click();
    }

    function handleDrop(files: FileList): void {
        addFiles(files);
    }

    function handleFileChange(e: Event): void {
        const input = e.currentTarget as HTMLInputElement;
        if (input.files?.length) {
            addFiles(input.files);
            input.value = ''; // reset so same file can be re-selected
        }
    }
</script>

<div
    class="input-container"
    class:renderBlock={true}
>
    <div
        class="upload-container"
        role="button"
        tabindex={uploadProgress === undefined ? 0 : -1}
        aria-disabled={uploadProgress !== undefined}
        onclick={browse}
        onkeydown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                browse();
            }
        }}
        use:dragDrop={{
            onDrop: handleDrop,
            accept: allowedMimeTypes,
            onDragState: (s) => (dragState = s)
        }}
    >
        <input
            type="file"
            multiple
            accept={acceptFilter}
            class="file-upload-input"
            style="display:none;"
            bind:this={fileInput}
            onchange={handleFileChange}
        />

        <div class="content-box" aria-busy={uploadProgress !== undefined}>
            {#if uploadProgress !== undefined}
                <div class="upload-progress-box">
                    <div class="upload-progress-ring">
                        <RadialProgress value={uploadProgress} size={64} strokeWidth={6} />
                        <span class="upload-progress-percent">{uploadProgress}%</span>
                    </div>
                </div>
            {:else}
                <span class="icon">
                    <FileUploadIcon size="1em" />
                </span>
                <div class="text-wrapper">
                    <p class="u-label">{__('assistants.builder.knowledge.upload_title')}</p>
                    <p class="description">
                        {__('assistants.builder.knowledge.upload_hint')}
                        {#if maxFileSize > 0}
                            <Tooltip focusable={false} hiddenLabel={restrictionText}>
                                {#snippet children({props})}
                                    <span {...props} class="hint-icon" aria-hidden="true">
                                        <InformationCircleIcon size="13" />
                                    </span>
                                {/snippet}
                                {#snippet tooltip()}
                                    <div class="restriction-label">
                                        {__('assistants.builder.knowledge.upload_max_size')}
                                    </div>
                                    <div class="restriction-value">{formatSize(maxFileSize)}</div>
                                    <div class="restriction-divider" aria-hidden="true"></div>
                                    <div class="restriction-label">
                                        {__('assistants.builder.knowledge.upload_allowed_extensions')}
                                    </div>
                                    <div class="restriction-value">{extensionDisplay}</div>
                                {/snippet}
                            </Tooltip>
                        {/if}
                    </p>
                </div>
                <!-- No own click handler: the click bubbles to the drop section,
                     which opens the picker — one code path for both. -->
                <Button
                    variant="fill"
                    size="md"
                    type="button">{__('assistants.builder.knowledge.upload_browse')}</Button
                >
            {/if}
        </div>

        {#if dragState !== 'idle'}
            <DragDropOverlay
                status={dragState}
                files={currentFiles}
            />
        {/if}
    </div>

    {#if currentFiles.length > 0}
        <div class="attachments-container">
            <div class="attachments-list">
                <GenericItemList label={__('assistants.builder.knowledge.attached_files')}>
                    {#each currentFiles as file, index (index)}
                        <Item
                            label={file.name}
                            description={[formatSize(file.size), formatDate(file.date), formatStatus(file)]
                                .filter(Boolean)
                                .join(' · ')}
                            icon={File01Icon}
                            onDelete={() => removeFile(index)}
                        />
                    {/each}
                </GenericItemList>
            </div>
        </div>
    {/if}
</div>

<style>
    .upload-container {
        position: relative;
        display: flex;
        justify-content: center;
        border: 1.5px dashed var(--color-border);
        border-radius: var(--corner-md);
        width: 100%;
        background-color: var(--color-surface-raised);
        cursor: pointer;
        transition:
            border-color var(--duration-fast),
            background-color var(--duration-fast);
    }
    .upload-container:hover,
    .upload-container:focus-visible {
        border-color: var(--color-accent-300);
        background-color: var(--color-hover);
    }
    .upload-container:has(.upload-progress-box) {
        pointer-events: none; /* uploading — no drop, click or hover feedback */
    }
    .upload-progress-box {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: var(--space-8);
    }
    .upload-progress-ring {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--color-text-muted);
    }
    .upload-progress-percent {
        position: absolute;
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
        color: var(--color-text-muted);
        font-variant-numeric: tabular-nums;
    }
    .content-box {
        display: flex;
        flex-direction: column;
        padding: var(--space-8);
        gap: var(--space-2);
        width: max-content;
        height: 100%;
        justify-content: center;
        align-items: center;
    }
    .content-box .icon {
        font-size: var(--font-size-2xl);
        color: var(--color-text-muted);
    }
    .text-wrapper {
        margin-bottom: var(--space-2);
        text-align: center;
    }
    .text-wrapper .label {
        margin-bottom: var(--space-0_5);
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        text-align: center;
    }
    .text-wrapper .description {
        margin: 0;
        font-size: var(--font-size-xs);
        color: var(--color-text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: var(--space-1);
    }
    .text-wrapper .description .hint-icon {
        display: inline-flex;
        color: var(--color-text-muted);
        cursor: help;
        line-height: 0;
    }
    .restriction-label {
        font-weight: var(--font-weight-medium);
        color: var(--color-text-muted);
    }
    .restriction-value {
        margin-block: var(--space-0_5);
    }
    .restriction-divider {
        height: 1px;
        background: var(--color-border);
        margin-block: var(--space-1);
    }
    .attachments-container {
        margin-top: var(--space-4);
    }
</style>
