import z from 'zod';

export const uploadFileStatuses = ['pending', 'uploading', 'complete', 'error'] as const;

export const UploadFileStatusSchema = z.enum(uploadFileStatuses);

export type UploadFileStatus = z.infer<typeof UploadFileStatusSchema>;

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
    /** Upload progress, 0–100. */
    progress: z.number().optional(),
    /** Current upload lifecycle state, mirrored from HAWKI's status classes. */
    status: UploadFileStatusSchema.optional(),
    /** Last user-facing error message for this file (upload failure, etc.). */
    error: z.string().optional(),
    /** Abort controller for cancelling an in-flight upload. */
    abortController: AbortControllerSchema.optional()
});

export type UploadFile = z.infer<typeof UploadFileSchema>;
