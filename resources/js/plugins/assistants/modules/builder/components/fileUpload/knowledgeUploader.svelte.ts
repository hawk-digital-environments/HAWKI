import { useBuilderContext, type BuilderContext } from "$plugins/assistants/modules/builder/contexts/BuilderContext.svelte.js";
import type { UploadFile } from "$plugins/assistants/types/UploadFile";
import { ApiError } from "$plugins/assistants/api/errors";
import {
    uploadAssistantAttachmentQueue,
    deleteAssistantAttachment,
} from "$plugins/assistants/api/resources/assistantAttachmentClient";
import { isAiToolAvailableFor, knowledgeToolsOf } from "$plugins/core/stores/aiToolStoreData.js";
import { useToastContext, type ToastContext } from "$lib/components/ui/toast/ToastContext.svelte";
import { useTranslator } from "$lib/app/hooks/useTranslator.svelte";
import { useConfig } from "$lib/app/hooks/useConfig.svelte";
import { useStore } from "$lib/app/hooks/useStore.svelte";

/** Why the draft cannot take knowledge uploads right now. */
export type KnowledgeUploadBlock = 'no-model' | 'no-knowledge-tool';

/**
 * # Knowledge uploader
 *
 * Uploads files into the draft assistant's knowledge (`builder.draft.files`)
 * — one implementation shared by the knowledge page's dropzone
 * (`FileUpload.svelte`) and the builder's guide chat. Owns the upload
 * constraints, the "may upload at all" gate and the per-file upload status;
 * RAG ingestion watching stays with the knowledge page (`ragIngestion.ts`),
 * which picks up every file left in `ragStatus: 'pending'` here.
 *
 * Must be created during component initialisation (it reads contexts).
 */
export class KnowledgeUploader {
    private readonly builder: BuilderContext = useBuilderContext();
    private readonly toast: ToastContext = useToastContext();
    private readonly translator = useTranslator();
    private readonly config = useConfig();
    private readonly modelStore = useStore('ai-models');
    private readonly toolStore = useStore('ai-tools');

    /** Guards against duplicate in-flight delete requests (rapid trash-icon clicks). */
    private deleting = false;

    /**
     * RAG mode: uploads continue into the knowledge-base ingestion pipeline
     * after the HTTP upload finishes, so the file lands in the dataset of the
     * selected model's knowledge-base tool.
     */
    readonly ragEnabled = $derived(this.config.rag?.enabled === true);

    /** Config-driven upload constraints (`storage_files` is absent while uploads are disabled). */
    readonly allowedMimeTypes = $derived<string[]>(this.config.storage_files?.allowedMimeTypes ?? []);
    readonly allowedExtensions = $derived<string[]>(this.config.storage_files?.allowedExtensions ?? []);
    readonly maxFileSize = $derived<number>(this.config.storage_files?.maxFileSize ?? 0);

    /** Accept filter for a native file picker: config MIME types plus dot-prefixed extensions. */
    readonly acceptFilter = $derived(
        [...this.allowedMimeTypes, ...this.allowedExtensions.map((ext) => `.${ext}`)].join(',') || undefined,
    );

    readonly files = $derived((this.builder.draft.files ?? []) as UploadFile[]);

    /**
     * Mean progress (0–100) over the currently uploading/pending batch;
     * `undefined` while no upload is in flight.
     */
    readonly uploadProgress = $derived.by(() => {
        const active = this.files.filter((f) => f.status === 'uploading' || f.status === 'pending');
        if (active.length === 0) return undefined;
        return Math.round(active.reduce((sum, f) => sum + (f.progress ?? 0), 0) / active.length);
    });

    private readonly currentModel = $derived(this.modelStore.getOneById(this.builder.draft.model));

    /**
     * Why uploads are currently not possible, or `null` when they are. With
     * rag on, uploading requires a model whose knowledge-base tool the
     * assistant can use — the files are preassembled into that dataset. With
     * rag off, files are injected per request, so uploads are always allowed.
     */
    readonly blockedReason = $derived.by((): KnowledgeUploadBlock | null => {
        if (!this.ragEnabled) return null;
        const model = this.currentModel;
        if (!model) return 'no-model';
        return knowledgeToolsOf(this.toolStore.tools).some((t) => isAiToolAvailableFor(t, model))
            ? null
            : 'no-knowledge-tool';
    });

    readonly disabled = $derived(this.blockedReason !== null);

    /**
     * Client-side type filter applied before queueing an upload. Unconfigured
     * lists, extension-less files and unknown MIME types pass through so the
     * server remains the real enforcer.
     */
    isFileAccepted(file: File): boolean {
        if (this.allowedMimeTypes.length === 0 && this.allowedExtensions.length === 0) return true;
        const dot = file.name.lastIndexOf('.');
        const ext = dot !== -1 ? file.name.slice(dot + 1).toLowerCase() : undefined;
        if (ext === undefined) return true;
        const mime = file.type.toLowerCase();
        return this.allowedExtensions.includes(ext) || (mime !== '' && this.allowedMimeTypes.includes(mime));
    }

    /** Replace the draft's files array, patching the entry whose local `file` reference matches. */
    private patchFile(fileRef: File | undefined, patch: Partial<UploadFile>): void {
        const next = (this.builder.draft.files ?? []).map((f) => (f.file === fileRef ? { ...f, ...patch } : f));
        this.builder.set('files', next);
    }

    /**
     * Queue the files on the draft and upload them right away. Rejected and
     * failed files are toasted and dropped.
     *
     * @returns The files that were uploaded, as they now stand in the draft.
     */
    async addFiles(fileList: FileList | File[]): Promise<UploadFile[]> {
        const __ = this.translator.__;
        if (this.disabled || this.uploadProgress !== undefined) return [];

        const incoming = Array.from(fileList);
        const accepted = incoming.filter((f) => this.isFileAccepted(f));
        for (const rejected of incoming.filter((f) => !this.isFileAccepted(f))) {
            this.toast.error(`${rejected.name}: ${__('assistants.builder.knowledge.upload_rejected_type')}`);
        }
        if (accepted.length === 0) return [];

        const queued: UploadFile[] = accepted.map((file) => ({
            name: file.name,
            size: file.size,
            mimeType: file.type,
            date: new Date(),
            file,
            status: 'pending',
            progress: 0,
        }));
        this.builder.set('files', [...(this.builder.draft.files ?? []), ...queued]);

        const assistantId = this.builder.draft.id;
        if (!assistantId) return [];

        queued.forEach((q) => this.patchFile(q.file, { status: 'uploading', progress: 0 }));

        const results = await uploadAssistantAttachmentQueue(
            assistantId,
            accepted,
            // Progress only — don't flip status to "complete" here: the byte
            // stream can finish and still yield a 422.
            (file, progress) => this.patchFile(file, { progress }),
        );

        // Reconcile per result: mark successes, drop failures with a toast.
        const uploaded: UploadFile[] = [];
        const next: UploadFile[] = [];
        for (const f of this.builder.draft.files ?? []) {
            const idx = queued.findIndex((q) => q.file === f.file);
            if (idx === -1) {
                next.push(f); // not part of this batch — keep untouched
                continue;
            }
            const result = results[idx];
            if (result?.error) {
                const reason =
                    result.error.errors[0]?.detail ?? result.error.fieldErrors[0]?.message ?? result.error.userMessage;
                this.toast.error(`${f.name}: ${reason}`);
                continue; // drop the failed file from the list
            }
            const done: UploadFile = this.ragEnabled && result?.uuid
                // Upload done, ingestion just started — the file only becomes
                // "complete" when the RAG pipeline reports `ingested`.
                ? { ...f, status: 'ingesting', progress: 100, uuid: result.uuid, ragStatus: 'pending' }
                : { ...f, status: 'complete', progress: 100, ...(result?.uuid ? { uuid: result.uuid } : {}) };
            next.push(done);
            uploaded.push(done);
        }
        this.builder.set('files', next);
        return uploaded;
    }

    /**
     * Remove a file. A persisted file (one with a uuid) is deleted server-side
     * first; on failure the entry is kept so the user can retry.
     *
     * @returns Whether the file is gone.
     */
    async removeFile(target: UploadFile): Promise<boolean> {
        if (this.deleting) return false;

        const assistantId = this.builder.draft.id;
        if (assistantId && target.uuid) {
            this.deleting = true;
            try {
                await deleteAssistantAttachment(assistantId, target.uuid);
            } catch (err) {
                const apiErr = ApiError.from(err);
                const reason = apiErr.errors[0]?.detail ?? apiErr.fieldErrors[0]?.message ?? apiErr.userMessage;
                this.toast.error(`${target.name}: ${reason}`);
                return false;
            } finally {
                this.deleting = false;
            }
        }
        const isTarget = (f: UploadFile): boolean => target.uuid
            ? f.uuid === target.uuid
            : f.name === target.name && f.file === target.file;
        this.builder.set('files', (this.builder.draft.files ?? []).filter((f) => !isTarget(f)));
        return true;
    }
}
