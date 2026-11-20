<script lang="ts">
    import FileUploadIcon from '$lib/components/ui/icons/iconset/FileUploadIcon.svelte';
    import File01Icon from '$lib/components/ui/icons/iconset/File01Icon.svelte';
    import Database01Icon from '$lib/components/ui/icons/iconset/Database01Icon.svelte';
    import FileDropHint from '$lib/components/ui/file-drop/FileDropHint.svelte';
    import { useBuilderContext } from '$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js';
    import type { UploadFile } from '$plugins/assistants/types/UploadFile';
    import GenericItemList from '$plugins/assistants/components/itemList/GenericItemList.svelte';
    import Item from '$plugins/assistants/components/itemList/Item.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import { dragDrop } from '$plugins/assistants/actions/dragDrop.svelte.js';
    import { formatFileSize } from '$plugins/assistants/utils/formatFileSize';
    import { useToastContext } from '$lib/components/ui/toast/ToastContext.svelte';
    import { deleteAssistantAttachment } from '$plugins/assistants/api/resources/assistantAttachmentClient';
    import { ApiError } from '$plugins/assistants/api/errors';
    import { KnowledgeUploader } from '$plugins/assistants/modules/builder/components/fileUpload/knowledgeUploader.svelte.js';
    import {
        watchRagIngestion,
        type RagFileState,
        type RagIngestionWatcher
    } from '$plugins/assistants/modules/builder/components/fileUpload/ragIngestion.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import { ActionIcon, StatusIcon } from '$lib/components/ui/icons';
    import LoadingBeam from '$lib/components/ui/loading-beam/LoadingBeam.svelte';
    import { wipeCollapse } from '$lib/utils/transitions/wipeCollapse';
    import InformationCircleIcon from '$lib/components/ui/icons/iconset/InformationCircleIcon.svelte';
    import DropdownMenu from '$lib/components/ui/dropdown-menu/DropdownMenu.svelte';
    import DropdownMenuItem from '$lib/components/ui/dropdown-menu/DropdownMenuItem.svelte';
    import ConfirmDialog from '$lib/components/ui/dialog/ConfirmDialog.svelte';
    import MoreVerticalSquare02Icon from '$lib/components/ui/icons/iconset/MoreVerticalSquare02Icon.svelte';
    import ArchiveOff04Icon from '$lib/components/ui/icons/iconset/ArchiveOff04Icon.svelte';
    import Delete02Icon from '$lib/components/ui/icons/iconset/Delete02Icon.svelte';
    import { mergeProps } from 'bits-ui';
    import { onDestroy } from 'svelte';

    const { __, hasLabel } = useTranslator();
    const toast = useToastContext();
    const builder = useBuilderContext();

    /**
     * Upload constraints, the "may upload" gate and the upload itself are
     * shared with the builder's guide chat (see `knowledgeUploader.svelte.ts`).
     * The dropzone is disabled only while the selected model cannot use any
     * knowledge-base tool; a missing model never blocks the upload.
     */
    const uploadId = $props.id();
    const uploader = new KnowledgeUploader(uploadId);
    const disabled = $derived(uploader.disabled);
    const disabledHint = $derived(
        uploader.blockedReason === 'no-knowledge-tool' ?
            __('assistants.builder.knowledge.upload_disabled_model_not_configured')
        :   undefined
    );

    let currentFiles = $derived(uploader.files);
    /**
     * The upload transport reports no byte progress (0, then 100 right before
     * the file is added), so the ring would vanish unfilled. On success it
     * stays at 100% for `FILLED_HOLD_MS` so the fill is seen before it goes.
     */
    const FILLED_HOLD_MS = 600;
    let filledProgress = $state<number | undefined>();
    let uploadProgress = $derived(uploader.uploadProgress ?? filledProgress);

    let fileInput = $state<HTMLInputElement | null>(null);

    /** Files are dragged over the drop zone; swaps its content for the chat's drop hint. */
    let isDragging = $state(false);

    /**
     * RAG mode: uploads continue into the knowledge-base ingestion pipeline
     * after the HTTP upload finishes. The UI workflow (ingesting state,
     * polling, disabled row with an error tooltip on failure) lives in
     * `./fileUpload/ragIngestion.js` + the handlers below and is skipped
     * entirely when RAG is disabled — the plain upload path stays as is.
     */
    const ragEnabled = $derived(uploader.ragEnabled);

    let watcher: RagIngestionWatcher | null = null;

    function ensureWatcher(assistantId: string): RagIngestionWatcher {
        watcher ??= watchRagIngestion(assistantId, handleRagUpdate);
        return watcher;
    }

    function patchByUuid(uuid: string, patch: Partial<UploadFile>): void {
        builder.set(
            'files',
            (builder.draft.files ?? []).map((f) => (f.uuid === uuid ? { ...f, ...patch } : f))
        );
    }

    /** A row whose ingestion failed — kept, but rendered disabled with the error in its tooltip. */
    function isRagFailed(file: UploadFile): boolean {
        return file.ragStatus === 'failed' || file.ragStatus === 'skipped';
    }

    /**
     * Display rank for `visibleFiles`: 0 = failed ingestion, 1 = still in
     * flight (uploading or ingesting), 2 = settled.
     */
    function displayRank(file: UploadFile): number {
        if (isRagFailed(file)) return 0;
        return file.status === 'pending' || file.status === 'uploading' || file.status === 'ingesting' ? 1 : 2;
    }

    /**
     * Rows in display order: failed ingestions first (disabled rows that
     * need attention), then files still uploading or ingesting, then the
     * settled ones — each group in draft order. View-only — the draft
     * itself keeps the server/upload order.
     */
    let visibleFiles = $derived([...currentFiles].sort((a, b) => displayRank(a) - displayRank(b)));

    /**
     * Stable identity for keyed rendering
     */
    function keyOf(file: UploadFile): string {
        return file.localKey ?? file.uuid ?? `${file.name}::${file.size}`;
    }

    /**
     * Rows with a server delete in flight — they show the confirming
     * treatment (sheen sweep + LoadingBeam + inert) until the request
     * settles; the row then leaves through the wipe+collapse exit.
     */
    let confirming = $state(new Set<string>());

    function markConfirming(file: UploadFile, on: boolean): void {
        const key = keyOf(file);
        const next = new Set(confirming);
        on ? next.add(key) : next.delete(key);
        confirming = next;
    }

    /** Persisted rows — what the manage menu's bulk deletes act on. */
    let persistedFiles = $derived(currentFiles.filter((f) => f.uuid));
    /** Whether the "delete failed files" menu item applies (RAG failures present). */
    let hasFailedFiles = $derived(ragEnabled && currentFiles.some(isRagFailed));

    /** Manage-menu state: confirm dialogs for the two bulk deletes + in-flight guard. */
    let deleteAllOpen = $state(false);
    let deleteFailedOpen = $state(false);
    let batchDeleting = $state(false);

    /** In-flight guard for a single row's server-side delete (see `removeFile`). */
    let deleting = $state(false);

    /**
     * Deletes the given rows server-side and drops the succeeded ones from
     * the draft in one update. Rows whose delete fails stay put so they can
     * be retried individually (where the per-file reason is toasted); here
     * one aggregate toast reports that something failed.
     */
    async function deleteFiles(targets: UploadFile[]): Promise<void> {
        if (batchDeleting || deleting || uploadProgress !== undefined) return; // no deletes while an upload batch is in flight — it would race the batch reconcile
        const assistantId = builder.draft.id;
        if (!assistantId) return;

        batchDeleting = true;
        const deleted = new Set<string>();
        let failedCount = 0;
        for (const file of targets) {
            if (file.uuid) markConfirming(file, true);
        }
        try {
            for (const file of targets) {
                if (!file.uuid) continue;
                try {
                    await deleteAssistantAttachment(assistantId, file.uuid);
                    deleted.add(file.uuid);
                } catch {
                    failedCount++;
                }
            }
        } finally {
            batchDeleting = false;
        }

        if (deleted.size > 0) {
            for (const uuid of deleted) watcher?.untrack(uuid);
            builder.set(
                'files',
                (builder.draft.files ?? []).filter((f) => !f.uuid || !deleted.has(f.uuid))
            );
        }
        confirming = new Set();
        if (failedCount > 0) {
            toast.error(__('assistants.builder.knowledge.delete_all_error'));
        }
    }

    /**
     * User-facing ingestion failure text: the stored translation key
     * (`rag_user_error`) when it resolves, otherwise the generic failure
     * message (legacy rows written before codes existed). The technical
     * `ragError` detail is never rendered.
     */
    function ragUserMessage(userError: string | null | undefined): string {
        return userError !== null && userError !== undefined && hasLabel(userError) ?
                __(userError)
            :   __('rag.ingestion.file_error');
    }

    /**
     * Poll outcome for one watched attachment: `ingested` completes the file,
     * interim states keep it "ingesting", and a `failed`/`skipped` pipeline
     * result disables the row and surfaces the user-facing failure reason
     * via toast and tooltip — the attachment itself stays persisted, so the
     * failure survives a builder reload.
     */
    function handleRagUpdate({
        uuid,
        state,
        error,
        userError
    }: {
        uuid: string;
        state: RagFileState;
        error: string | null;
        userError: string | null;
    }): void {
        if (state === 'failed' || state === 'skipped') {
            const name = (builder.draft.files ?? []).find((f) => f.uuid === uuid)?.name ?? uuid;
            toast.error(
                __('rag.ingestion_failed', {
                    name,
                    reason: ragUserMessage(userError)
                })
            );
            patchByUuid(uuid, {
                ragStatus: state,
                ragError: error,
                ragUserError: userError,
                status: 'error',
                error: undefined
            });
            return;
        }
        patchByUuid(uuid, {
            ragStatus: state,
            ragError: null,
            ragUserError: null,
            status: state === 'ingested' ? 'complete' : 'ingesting',
            progress: 100
        });
    }

    // Watch attachments whose ingestion is still running — fresh uploads
    // (tracked in addFiles) as well as files restored from the server when
    // the builder (re)opens. Files that already failed while it was closed
    // need no handling here: they arrive with their final `ragStatus`/
    // `ragError` and simply render as disabled rows.
    $effect(() => {
        if (!ragEnabled) return;
        const assistantId = builder.draft.id;
        if (!assistantId) return;

        for (const file of builder.draft.files ?? []) {
            if (!file.uuid || !file.ragStatus) continue;
            if (file.ragStatus === 'pending' || file.ragStatus === 'ingesting') {
                ensureWatcher(assistantId).track(file.uuid, file.ragStatus);
            }
        }
    });

    onDestroy(() => watcher?.stop());

    const allowedMimeTypes = $derived(uploader.allowedMimeTypes);
    const allowedExtensions = $derived(uploader.allowedExtensions);
    const maxFileSize = $derived(uploader.maxFileSize);
    const acceptFilter = $derived(uploader.acceptFilter);

    /** Human-readable extension list shared by the tooltip and its screen-reader label. */
    const extensionDisplay = $derived(allowedExtensions.map((ext) => `.${ext}`).join(', '));
    const restrictionText = $derived(
        `${__('assistants.builder.knowledge.upload_max_size')}: ${formatFileSize(maxFileSize)}. ` +
            `${__('assistants.builder.knowledge.upload_allowed_extensions')}: ${extensionDisplay}`
    );

    function formatDate(date?: Date): string {
        if (!date) return '';
        const d = date instanceof Date ? date : new Date(date);
        return d.toLocaleDateString();
    }

    /** RAG status indicator shown at a file row's right edge — see `trailing` in the attachments list. */
    interface RagIndicator {
        /** Tooltip status word, combined as "RAG status: {label}". */
        label: string;
        /** Semantic color class for the knowledge-database icon. */
        cls: 'success' | 'pending' | 'failed';
        /** Resolved user-facing failure reason, shown below the status line in the tooltip. */
        error?: string;
    }

    /**
     * Maps a file's server-side `ragStatus` to the per-row indicator: a
     * knowledge-database icon colored by state (green = ingested, yellow =
     * running, red = failed). Only applies to persisted files in RAG mode;
     * returns `undefined` otherwise (non-rag files carry no ingestion state).
     */
    function ragIndicator(file: UploadFile): RagIndicator | undefined {
        if (!ragEnabled || !file.uuid) return undefined;
        switch (file.ragStatus) {
            case 'ingested':
                return { label: __('rag.status_success'), cls: 'success' };
            case 'pending':
            case 'ingesting':
                return { label: __('rag.status_pending'), cls: 'pending' };
            case 'failed':
            case 'skipped':
                return {
                    label: __('rag.status_failed'),
                    cls: 'failed',
                    error: ragUserMessage(file.ragUserError)
                };
            default:
                return undefined;
        }
    }

    /** Human-readable upload state appended to each file's description. */
    function formatStatus(file: UploadFile): string {
        switch (file.status) {
            case 'pending':
                return __('assistants.builder.knowledge.upload_title');
            case 'uploading':
                return `${file.progress ?? 0}%`;
            case 'ingesting':
                return __('rag.ingesting');
            case 'error':
                // Rag failures carry their reason in the indicator tooltip —
                // the description only shows the localized status word.
                if (isRagFailed(file)) return __('rag.status_failed');
                return file.error ?? 'error';
            default:
                return '';
        }
    }

    // --- File handling ---
    // Watching a fresh upload's RAG ingestion needs nothing here: the uploader
    // leaves it in `ragStatus: 'pending'`, which the effect above tracks.
    async function addFiles(fileList: FileList): Promise<void> {
        // Set on the 100% tick, before the uploader marks the files complete,
        // so the ring stays mounted and animates its fill.
        const uploaded = await uploader.addFiles(fileList, (_file, progress) => {
            if (progress === 100) filledProgress = 100;
        });
        if (uploaded.length === 0) {
            filledProgress = undefined;
            return;
        }
        setTimeout(() => (filledProgress = undefined), FILLED_HOLD_MS);
    }

    /**
     * Remove a file. If it has been persisted (has a uuid), the server copy is
     * deleted first via the attachment delete action; the row shows the
     * confirming treatment (sheen + beam + inert) while that runs, and on
     * failure the local entry is kept so the user can retry.
     *
     * Unify with hawki frontend function `requestAtchDelete` when migrating to
     * hawki frontend. Ported from HAWKI/public/js/attachment_handler.js:68.
     */
    async function removeFile(target: UploadFile): Promise<void> {
        if (deleting || uploadProgress !== undefined) return; // no deletes while an upload batch is in flight — it would race the batch reconcile
        const files = builder.draft.files ?? [];
        if (!files.includes(target)) return;

        const assistantId = builder.draft.id;
        if (assistantId && target.uuid) {
            deleting = true;
            markConfirming(target, true);
            try {
                await deleteAssistantAttachment(assistantId, target.uuid);
                watcher?.untrack(target.uuid);
            } catch (err) {
                const apiErr = ApiError.from(err);
                const reason = apiErr.errors[0]?.detail ?? apiErr.fieldErrors[0]?.message ?? apiErr.userMessage;
                toast.error(`${target.name}: ${reason}`);
                markConfirming(target, false);
                return; // keep the row — surface the failure via toast
            } finally {
                deleting = false;
            }
        }
        markConfirming(target, false);
        // Re-resolved against the live draft (a batch reconcile may have
        // replaced rows while the delete request was in flight).
        builder.set(
            'files',
            (builder.draft.files ?? []).filter((f) => f !== target && f.uuid !== target.uuid)
        );
    }

    function browse(): void {
        if (disabled) return;
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
        class:isDisabled={disabled}
        class:isDragging
        role="button"
        tabindex={disabled ? -1 : 0}
        aria-disabled={disabled}
        onclick={browse}
        onkeydown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                browse();
            }
        }}
        use:dragDrop={{
            onDrop: handleDrop,
            onDragState: (s) => (isDragging = s !== 'idle')
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

        <div
            class="content-box"
            class:content-box--hidden={isDragging}
            aria-busy={uploadProgress !== undefined}
        >
            <div
                class="upload-icon-wrap"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={uploadProgress}
                aria-label={__('assistants.builder.knowledge.upload_progress')}
            >
                <div
                    class="upload-icon"
                    class:upload-icon--faded={uploadProgress !== undefined}
                >
                    <StatusIcon
                        icon={FileUploadIcon}
                        tone="neutral"
                        size="xl"
                    />
                </div>
                {#if uploadProgress !== undefined}
                    <LoadingBeam />
                    <span
                        class="upload-progress-center"
                        aria-hidden="true">{uploadProgress}%</span
                    >
                {/if}
            </div>
            <div class="text-wrapper">
                <p class="u-label">{__('assistants.builder.knowledge.upload_title')}</p>
                <p class="description">
                    {__('assistants.builder.knowledge.upload_hint')}
                    {#if maxFileSize > 0}
                        <Tooltip
                            focusable={false}
                            hiddenLabel={restrictionText}
                        >
                            {#snippet children({ props })}
                                <span
                                    {...props}
                                    class="hint-icon"
                                    aria-hidden="true"
                                >
                                    <InformationCircleIcon size="13" />
                                </span>
                            {/snippet}
                            {#snippet tooltip()}
                                <div class="restriction-label">
                                    {__('assistants.builder.knowledge.upload_max_size')}
                                </div>
                                <div class="restriction-value">{formatFileSize(maxFileSize)}</div>
                                <div
                                    class="restriction-divider"
                                    aria-hidden="true"
                                ></div>
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
        </div>

        {#if isDragging}
            <FileDropHint label={__('assistants.builder.guide.drop_label')} />
        {/if}
    </div>

    {#if disabled && disabledHint}
        <div class="disabled-hint">
            <Tooltip
                focusable={false}
                hiddenLabel={disabledHint}
            >
                {#snippet children({ props })}
                    <span
                        {...props}
                        class="hint-icon"
                        aria-hidden="true"
                    >
                        <InformationCircleIcon size="14" />
                    </span>
                {/snippet}
                {#snippet tooltip()}
                    {disabledHint}
                {/snippet}
            </Tooltip>
        </div>
    {/if}

    <div class="attachments-container">
        <div class="attachments-list">
            <GenericItemList label={__('assistants.builder.knowledge.attached_files')}>
                {#snippet actions()}
                    {#if currentFiles.length === 0}
                        <!-- Nothing to manage — a disabled placeholder keeps the
                         header slot aligned with the rows' delete icons. -->
                        <Tooltip
                            focusable={false}
                            hiddenLabel={__('assistants.builder.knowledge.no_files')}
                        >
                            {#snippet children({ props })}
                                <ActionIcon
                                    {...props}
                                    icon={ArchiveOff04Icon}
                                    label={__('assistants.builder.knowledge.no_files')}
                                    size="md"
                                    disabled
                                />
                            {/snippet}
                            {#snippet tooltip()}
                                {__('assistants.builder.knowledge.no_files')}
                            {/snippet}
                        </Tooltip>
                    {:else}
                        <ConfirmDialog
                            bind:open={deleteAllOpen}
                            title={__('assistants.builder.knowledge.delete_all_confirm_title')}
                            description={__('assistants.builder.knowledge.delete_all_confirm_description')}
                            okLabel={__('assistants.builder.knowledge.delete_all_files')}
                            onConfirm={() => {
                                void deleteFiles(persistedFiles);
                            }}
                        />
                        {#if hasFailedFiles}
                            <ConfirmDialog
                                bind:open={deleteFailedOpen}
                                title={__('rag.delete_failed_confirm_title')}
                                description={__('rag.delete_failed_confirm_description')}
                                okLabel={__('rag.delete_failed_files')}
                                onConfirm={() => {
                                    void deleteFiles(currentFiles.filter(isRagFailed));
                                }}
                            />
                        {/if}
                        <DropdownMenu
                            align="end"
                            disabled={batchDeleting}
                        >
                            {#snippet trigger({ props })}
                                <Tooltip
                                    focusable={false}
                                    hiddenLabel={__('assistants.builder.knowledge.manage_files')}
                                >
                                    {#snippet children({ props: tooltipProps })}
                                        <ActionIcon
                                            {...mergeProps(props, tooltipProps)}
                                            icon={MoreVerticalSquare02Icon}
                                            label={__('assistants.builder.knowledge.manage_files')}
                                            size="md"
                                        />
                                    {/snippet}
                                </Tooltip>
                            {/snippet}
                            {#if hasFailedFiles}
                                <DropdownMenuItem
                                    variant="destructive"
                                    iconLeft={Delete02Icon}
                                    disabled={uploadProgress !== undefined}
                                    onclick={() => (deleteFailedOpen = true)}
                                >
                                    {__('rag.delete_failed_files')}
                                </DropdownMenuItem>
                            {/if}
                            <DropdownMenuItem
                                variant="destructive"
                                iconLeft={Delete02Icon}
                                disabled={uploadProgress !== undefined}
                                onclick={() => (deleteAllOpen = true)}
                            >
                                {__('assistants.builder.knowledge.delete_all_files')}
                            </DropdownMenuItem>
                        </DropdownMenu>
                    {/if}
                {/snippet}
                {#each visibleFiles as file (keyOf(file))}
                    {@const isConfirming = confirming.has(keyOf(file))}
                    {@const indicator = ragIndicator(file)}
                    {@const indicatorTooltip = indicator ? `${__('rag.status')}: ${indicator.label}` : ''}
                    <div
                        class="attachment-row"
                        class:confirming={isConfirming}
                        inert={isConfirming || undefined}
                        aria-busy={isConfirming}
                        out:wipeCollapse
                    >
                        <Item
                            label={file.name}
                            description={[formatFileSize(file.size), formatDate(file.date), formatStatus(file)]
                                .filter(Boolean)
                                .join(' · ')}
                            icon={File01Icon}
                            disabled={isRagFailed(file)}
                            busy={isConfirming}
                            deleteDisabled={uploadProgress !== undefined}
                            onDelete={() => removeFile(file)}
                        >
                            {#snippet trailing()}
                                {#if indicator}
                                    <Tooltip
                                        focusable={false}
                                        hiddenLabel={indicatorTooltip}
                                    >
                                        {#snippet children({ props })}
                                            <StatusIcon
                                                {...props}
                                                icon={Database01Icon}
                                                tone={indicator.cls === 'pending' ? 'warning'
                                                : indicator.cls === 'failed' ? 'error'
                                                : 'success'}
                                                size="md"
                                                class="rag-status"
                                                aria-hidden="true"
                                            />
                                        {/snippet}
                                        {#snippet tooltip()}
                                            <div>{indicatorTooltip}</div>
                                            {#if indicator.error}
                                                <div
                                                    class="restriction-divider"
                                                    aria-hidden="true"
                                                ></div>
                                                <div class="rag-error">{indicator.error}</div>
                                            {/if}
                                        {/snippet}
                                    </Tooltip>
                                {/if}
                            {/snippet}
                        </Item>
                    </div>
                {/each}
            </GenericItemList>
        </div>
    </div>
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
    .upload-container:focus-visible,
    .upload-container.isDragging {
        border-color: var(--color-accent-300);
        background-color: var(--color-hover);
    }
    .upload-container.isDisabled {
        pointer-events: none; /* disabled (e.g. no model selected) — no drop, click or hover feedback */
        opacity: 0.75;
        cursor: not-allowed;
    }
    /* Hosts the LoadingBeam overlay (it positions itself against this
       positioned ancestor and hugs `--loading-beam-radius`); the faded
       icon, the centered progress pill and the progressbar semantics ride
       on the same wrapper. */
    .upload-icon-wrap {
        position: relative;
        display: flex;
        --loading-beam-radius: var(--corner-sm);
        border-radius: var(--corner-sm);
    }
    .upload-icon {
        display: flex;
        transition:
            opacity var(--duration-fast),
            filter var(--duration-fast);
    }
    .upload-icon--faded {
        opacity: 0.3;
        filter: grayscale(1);
    }
    .upload-progress-center {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        padding: 0 0.375rem;
        border-radius: var(--corner-full);
        /* Button-grade pairing (accent fill + on-fill label): the fill stays
           dark in both themes, so the white label keeps contrast over the
           faded icon and the beam's glow. */
        background-color: var(--color-accent-fill);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-semibold);
        color: var(--color-on-accent-fill);
        font-variant-numeric: tabular-nums;
        line-height: 1.3;
        white-space: nowrap;
        pointer-events: none;
        z-index: 1;
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
    .content-box--hidden {
        visibility: hidden; /* keeps the dropzone's size while the drop hint shows */
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
    .disabled-hint {
        display: flex;
        justify-content: center;
        margin-top: var(--space-2);
    }
    .disabled-hint .hint-icon {
        display: inline-flex;
        color: var(--color-text-muted);
        cursor: help;
        line-height: 0;
    }
    :global(.rag-status) {
        cursor: help;
    }
    .rag-error {
        color: var(--color-text-muted);
    }
    /* One deleted list row: hosts the confirming treatment (sheen on the
       text card, dimmed opacity) and the exit transition (wipeCollapse). */
    .attachment-row {
        transition: opacity var(--duration-fast);
    }
    .attachment-row.confirming {
        opacity: 0.6;
    }
    .empty-message {
        margin: 0;
        font-size: var(--font-size-sm);
        color: var(--color-text-muted);
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
