import z from 'zod';

export const uploadFileStatuses = ['pending', 'uploading', 'ingesting', 'complete', 'error'] as const;

export const UploadFileStatusSchema = z.enum(uploadFileStatuses);

export type UploadFileStatus = z.infer<typeof UploadFileStatusSchema>;

/** An admin's judgment on the file, set from the Publishing Center's assistant detail page. */
export const attachmentReviewStatuses = ['ok', 'corrupted', 'inadequate'] as const;

export const AttachmentReviewStatusSchema = z.enum(attachmentReviewStatuses);

export type AttachmentReviewStatus = z.infer<typeof AttachmentReviewStatusSchema>;

/**
 * Browser-only handles that have no JSON representation. `z.custom` keeps them
 * typed without `z.instanceof`, which would need the global to exist at module
 * evaluation time (it does not under SSR/node test runs).
 */
const FileHandleSchema = z.custom<File>(
    value => typeof File !== 'undefined' && value instanceof File
);
const AbortControllerSchema = z.custom<AbortController>(
    value => typeof AbortController !== 'undefined' && value instanceof AbortController
);

/**
 * A file being attached to a resource (an assistant's knowledge files, a chat
 * attachment, ...), covering both the client-side upload lifecycle and the
 * persisted record.
 *
 * Only `name` is guaranteed: a freshly picked file has `file`/`progress`/`status`
 * but no `uuid`, while a file loaded back from the server has `uuid`/`size` but
 * no `File` handle. The `file`, `abortController` and `date` fields do not
 * survive a JSON round-trip — never rely on them after a session-storage restore.
 */
export const UploadFileSchema = z.object({
    id: z.number().optional(),
    name: z.string(),
    mimeType: z.string().optional(),
    size: z.number().optional(),
    /** Coerced, not strict: a file restored from storage carries an ISO string here, a freshly picked one a real `Date`. */
    date: z.coerce.date().optional(),
    file: FileHandleSchema.optional(),
    /** Server-assigned id once the upload has been persisted. */
    uuid: z.string().optional(),
    /** Storage identifier (`{category}-{uuid}.{ext}`) used by the `/proxy/storage/{identifier}` route to stream/preview/download the file. */
    identifier: z.string().optional(),
    /** Upload progress, 0–100. */
    progress: z.number().optional(),
    /** Current upload lifecycle state, mirrored from HAWKI's status classes. */
    status: UploadFileStatusSchema.optional(),
    /**
     * Server-side RAG ingestion state of the persisted attachment
     * (`assistant_attachments.rag_status`): `pending`/`ingesting` while the
     * knowledge-base pipeline runs, `ingested` on success, `failed`/`skipped`
     * when it never landed. `null` when RAG doesn't apply to this file.
     */
    ragStatus: z.string().nullable().optional(),
    /** Server-side ingestion failure reason (`assistant_attachments.rag_error`). */
    ragError: z.string().nullable().optional(),
    /** Admin review verdict (`assistant_attachments.review_status`); `null`/absent = not yet reviewed. */
    reviewStatus: AttachmentReviewStatusSchema.nullable().optional(),
    /** Last user-facing error message for this file (upload failure, etc.). */
    error: z.string().optional(),
    /** Abort controller for cancelling an in-flight upload. */
    abortController: AbortControllerSchema.optional()
});

export type UploadFile = z.infer<typeof UploadFileSchema>;
