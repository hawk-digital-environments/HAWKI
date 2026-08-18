<script lang="ts">
    import FileUploadIcon from '$lib/components/ui/icons/iconset/FileUploadIcon.svelte';
    import File01Icon from '$lib/components/ui/icons/iconset/File01Icon.svelte';
    import DragDropOverlay from '$lib/plugins/assistants/components/dragDropOverlay/DragDropOverlay.svelte';
    import { useBuilderContext } from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import type { UploadFile } from "$lib/plugins/assistants/types/UploadFile";
    import GenericItemList from "$lib/plugins/assistants/components/itemList/GenericItemList.svelte";
    import Item from "$lib/plugins/assistants/components/itemList/Item.svelte";
    import Button from "$lib/components/ui/button/Button.svelte";
    import {dragDrop} from "$lib/plugins/assistants/actions/dragDrop.svelte.js";
    import { useToastContext } from "$lib/components/ui/toast/ToastContext.svelte";
    import { ApiError } from "$lib/plugins/assistants/api/errors";
    import {
        uploadAssistantAttachmentQueue,
        deleteAssistantAttachment,
    } from "$lib/plugins/assistants/api/resources/assistantAttachmentClient";
    import {useTranslator} from "$lib/app/hooks/useTranslator.svelte";

    const {__} = useTranslator();
    const toast = useToastContext();
    const builder = useBuilderContext();

    let currentFiles = $derived((builder.draft.files ?? []) as UploadFile[]);

    /** True while at least one file is still uploading — drives the overlay. */
    let hasActiveUpload = $derived(
        currentFiles.some((f) => f.status === "uploading" || f.status === "pending"),
    );

    let fileInput = $state<HTMLInputElement | null>(null);

    /** Guards against duplicate in-flight delete requests (rapid trash-icon clicks). */
    let deleting = $state(false);

    /** Active drag-over state for the drop zone; drives the overlay badge. */
    let dragState = $state<"idle" | "valid" | "invalid">("idle");

    // TODO (HAWKI 2.5 integration): fetch the allowed MIME list from the config
    // API — GET /api/hawki/v1/configs/public →
    // data.attributes.list["hawki-core"].storage_files.allowedMimeTypes — and
    // replace this hardcoded list. The same fetch also yields allowedExtensions
    // (for <input accept>) and maxFileSize (for the hint text).
    const ACCEPTED_MIME = [
        "application/pdf",
        "text/plain",
        "text/csv",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/msword",
        "image/png",
        "image/jpeg",
        "image/gif",
    ];

    // TODO (HAWKI 2.5 integration): bind to storage_files.allowedExtensions
    // from the config API instead of this hardcoded extension mirror.
    const ACCEPTED_EXTENSIONS = ".pdf,.txt,.csv,.docx,.doc,.png,.jpeg,.gif";

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
            case "pending":
                return __("assistants.builder.knowledge.upload_title");
            case "uploading":
                return `${file.progress ?? 0}%`;
            case "error":
                return file.error ?? "error";
            default:
                return "";
        }
    }

    /** Replace the draft's files array, patching the entry whose local `file`
     *  reference matches. Mirrors the per-file status mutations HAWKI applied
     *  via the `SendMessageStatus` object. */
    function patchFile(fileRef: File | undefined, patch: Partial<UploadFile>): void {
        const next = (builder.draft.files ?? []).map((f) =>
            f.file === fileRef ? { ...f, ...patch } : f,
        );
        builder.set("files", next);
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
        const incoming = Array.from(fileList);
        const queued: UploadFile[] = incoming.map((file) => ({
            name: file.name,
            size: file.size,
            mimeType: file.type,
            date: new Date(),
            file,
            status: "pending",
            progress: 0,
        }));
        builder.set("files", [
            ...(builder.draft.files ?? []),
            ...queued,
        ]);

        const assistantId = builder.draft.id;
        if (!assistantId) return;

        queued.forEach((q) => patchFile(q.file, { status: "uploading", progress: 0 }));

        const results = await uploadAssistantAttachmentQueue(
            assistantId,
            incoming,
            // Progress only — don't flip status to "complete" here: axios fires
            // 100% when the byte stream finishes, which can still yield a 422.
            (file, progress) => patchFile(file, { progress }),
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
                    result.error.errors[0]?.detail ??
                    result.error.fieldErrors[0]?.message ??
                    result.error.userMessage;
                toast.error(`${f.name}: ${reason}`);
                continue; // drop the failed file from the list
            }
            next.push({
                ...f,
                status: "complete",
                progress: 100,
                ...(result?.uuid ? { uuid: result.uuid } : {}),
            });
        }
        builder.set("files", next);
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
                const reason =
                    apiErr.errors[0]?.detail ??
                    apiErr.fieldErrors[0]?.message ??
                    apiErr.userMessage;
                toast.error(`${target.name}: ${reason}`);
                return; // keep the row — surface the failure via toast
            } finally {
                deleting = false;
            }
        }
        builder.set("files", files.filter((_, i) => i !== index));
    }

    function browse(): void {
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


<div class="input-container" class:renderBlock={true}>
    <div class="upload-container"
         use:dragDrop={{
             onDrop: handleDrop,
             accept: ACCEPTED_MIME,
             onDragState: (s) => (dragState = s),
         }}
    >
        <input
                type="file"
                multiple
                accept={ACCEPTED_EXTENSIONS}
                class="file-upload-input"
                style="display:none;"
                bind:this={fileInput}
                onchange={handleFileChange}
        />

        <div class="content-box">
            <span class="icon"><FileUploadIcon size="1em" /></span>
            <div class="text-wrapper">
                <p class="label">{__('assistants.builder.knowledge.upload_title')}</p>
                <p class="description">{__('assistants.builder.knowledge.upload_hint')}</p>
            </div>
            <Button
                    variant="fill"
                    size="md"
                    onclick={browse}
            >{__('assistants.builder.knowledge.upload_browse')}</Button>
        </div>

        {#if dragState !== "idle" || hasActiveUpload}
            <DragDropOverlay status={dragState} files={currentFiles} />
        {/if}
    </div>

    {#if currentFiles.length > 0}
        <div class="attachments-container">
            <div class="attachments-list">
                <GenericItemList label={__('assistants.builder.knowledge.attached_files')}>
                    {#each currentFiles as file, index (index)}
                        <Item
                            label={file.name}
                            description={[formatSize(file.size), formatDate(file.date), formatStatus(file)].filter(Boolean).join(' · ')}
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
        transition:
            border-color var(--duration-fast),
            background-color var(--duration-fast);
    }
    .upload-container:hover {
        border-color: var(--color-accent-300);
        background-color: var(--color-hover);
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
    }
    .attachments-container {
        margin-top: var(--space-4);
    }
</style>
