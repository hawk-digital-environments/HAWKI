import { ApiError, logApiError } from "$lib/plugins/assistants/api/errors";

const TYPE = "assistants";

/**
 * Shape returned by a single attachment upload. `assistantId` is always
 * present (read from the JSON:API `data.id`); `uuid` is best-effort because
 * the action returns the full assistant resource rather than the bare
 * `{ uuid }` the legacy HAWKI endpoint returned. Callers that need a
 * guaranteed-complete files list should refetch the assistant afterwards.
 */
export interface AttachmentUploadResult {
    assistantId: string;
    uuid?: string;
}

/**
 * Per-file outcome of a queue upload. Exactly one of `uuid` (success) or
 * `error` (failure — e.g. 422 validation, 413 size, network) is set, so
 * callers can reconcile per file instead of guessing from a bare `undefined`.
 */
export interface AttachmentQueueResult {
    uuid?: string;
    error?: ApiError;
}

/**
 * Upload a single file to an assistant's knowledge attachments, with upload
 * progress tracking and cancellation support.
 *
 * Unify with hawki frontend function `uploadFileToServer` when migrating to
 * hawki frontend. Ported from HAWKI/public/js/file_manager.js:9 — the raw
 * XHR transport is replaced by `getApi().axios`, `xhr.upload.onprogress` by
 * axios `onUploadProgress`, and `xhr.abort()` by `AbortController`/`signal`.
 *
 * Unlike the legacy endpoint (which resolved with `{ uuid }`), the JSON:API
 * action returns the full assistant resource; the request sends
 * `?include=attachments` so the uploaded file's uuid is read reliably from the
 * inlined `included` collection (see {@link readAttachmentUuid}).
 *
 * @param assistantId  The assistant the file is attached to.
 * @param file         The file to upload.
 * @param onProgress   Optional callback receiving 0–100 percent.
 * @param signal       Optional AbortSignal to cancel the upload.
 */
export async function uploadAssistantAttachment(
    assistantId: string,
    file: File,
    onProgress?: (progress: number) => void,
    signal?: AbortSignal,
): Promise<AttachmentUploadResult> {
    const url = `${TYPE}/${assistantId}/actions/attachment`;
    const formData = new FormData();
    formData.append("file", file);

    try {
        const response = await getApi().axios.post(url, formData, {
            headers: { "Content-Type": "multipart/form-data" },
            // Request the attachments relationship inline so the uploaded file's
            // uuid can be read reliably from `included` (the uploader is the
            // creator, so the privileged include is permitted).
            params: { include: "attachments" },
            onUploadProgress: (event) => {
                if (!onProgress || !event.total) return;
                onProgress(Math.round((event.loaded / event.total) * 100));
            },
            signal,
        });

        const data = response.data?.data ?? {};
        const assistantIdOut = String(data.id ?? assistantId);
        return {
            assistantId: assistantIdOut,
            uuid: readAttachmentUuid(response.data, file.name),
        };
    } catch (err) {
        throw logApiError("uploadAssistantAttachment", err, { assistantId, file: file.name });
    }
}

/**
 * Upload every file in the queue to the assistant in parallel, invoking the
 * progress callback per file as each upload advances.
 *
 * Unify with hawki frontend function `uploadAttachmentQueue` when migrating
 * to hawki frontend. Ported from HAWKI/public/js/attachment_handler.js:145 —
 * the `Promise.all` fan-out and per-file uuid collection are preserved; the
 * `SendMessageStatus` side-effect object is replaced by an `onFileProgress`
 * callback and per-file `AbortSignal`s.
 *
 * @returns The list of per-file outcomes, in input order. Each entry carries
 *          either a server-assigned `uuid` (success) or an `error` (failure).
 *          Exactly one is set per entry, so callers can drop failures and toast.
 */
export async function uploadAssistantAttachmentQueue(
    assistantId: string,
    files: File[],
    onFileProgress?: (file: File, progress: number) => void,
    signal?: AbortSignal,
): Promise<AttachmentQueueResult[]> {
    // const uploadTasks = files.map((file) =>
    //     uploadAssistantAttachment(
    //         assistantId,
    //         file,
    //         (progress) => onFileProgress?.(file, progress),
    //         signal,
    //     )
    //         .then((result): AttachmentQueueResult => {
    //             onFileProgress?.(file, 100);
    //             return { uuid: result.uuid };
    //         })
    //         .catch((error): AttachmentQueueResult => {
    //             console.error(`Upload failed for ${file.name}:`, error);
    //             onFileProgress?.(file, 0);
    //             const apiError = error instanceof ApiError ? error : ApiError.from(error);
    //             return { error: apiError };
    //         }),
    // );
    //
    // return Promise.all(uploadTasks);
}

/**
 * Delete an attachment from an assistant by its file id.
 *
 * Unify with hawki frontend function `requestAtchDelete` when migrating to
 * hawki frontend. Ported from HAWKI/public/js/attachment_handler.js:68 — the
 * CSRF meta-token header is dropped (bearer-token interceptor handles auth)
 * and `fetch` is replaced by `getApi().axios`. The `{ fileId }` JSON body is
 * preserved verbatim.
 */
export async function deleteAssistantAttachment(
    assistantId: string,
    fileId: string,
): Promise<void> {
    const url = `${TYPE}/${assistantId}/actions/attachment`;
    try {
        await getApi().axios.delete(url, { data: { fileId } });
    } catch (err) {
        throw logApiError("deleteAssistantAttachment", err, { assistantId, fileId });
    }
}

/**
 * Extract the just-uploaded attachment's `uuid` attribute from a raw JSON:API
 * response. The upload action returns the full assistant resource; the
 * attachment we just added is identified inside `included` by matching its
 * `attributes.name` against the uploaded file's name (the backend stores the
 * original filename as the attachment `name`), with the max resource `id`
 * breaking ties when a file with the same name already existed.
 *
 * This returns the **`uuid` attribute** — the value `DELETE …/actions/attachment`
 * expects as `fileId` (it resolves `where('uuid', fileId)`), NOT the JSON:API
 * resource `id` (model PK), which the relationship linkage carries instead.
 *
 * Returns `undefined` when `included` is absent or no name match is found.
 */
function readAttachmentUuid(responseData: any, fileName: string): string | undefined {
    const included = responseData?.included;
    if (!Array.isArray(included)) return undefined;

    const matches = included.filter(
        (i: any) => i.type === "attachments" && i.attributes?.name === fileName,
    );
    if (matches.length === 0) return undefined;

    const newest = matches.reduce((a: any, b: any) =>
        Number(b.id) > Number(a.id) ? b : a,
    );
    const uuid = newest.attributes?.uuid;
    return uuid ? String(uuid) : undefined;
}
