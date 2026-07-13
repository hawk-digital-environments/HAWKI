import { ApiError, logApiError } from "$plugins/assistants/api/errors";
import { useApp } from "$lib/app/hooks/useApp.svelte";

const ASSISTANTS = "assistants";

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
 * Upload a single file to an assistant's knowledge attachments.
 *
 * Unlike the legacy endpoint (which resolved with `{ uuid }`), the JSON:API
 * action returns the full assistant resource; the request sends
 * `?include=attachments` so the uploaded file's uuid is read reliably from the
 * inlined `included` collection (see {@link readAttachmentUuid}).
 *
 * `onProgress` is best-effort (0 then 100): the kernel's `fetch`-based
 * transport has no upload-progress event, unlike the axios client this was
 * originally written against.
 *
 * @param assistantId  The assistant the file is attached to.
 * @param file         The file to upload.
 * @param onProgress   Optional callback receiving 0 or 100 percent.
 * @param signal       Optional AbortSignal to cancel the upload.
 */
export async function uploadAssistantAttachment(
    assistantId: string,
    file: File,
    onProgress?: (progress: number) => void,
    signal?: AbortSignal,
): Promise<AttachmentUploadResult> {
    const formData = new FormData();
    formData.append("file", file);

    try {
        onProgress?.(0);
        const response = await useApp().restApi.postToResourceAction(
            ASSISTANTS,
            `${assistantId}/actions/attachment?include=attachments`,
            formData,
            { signal },
        );
        onProgress?.(100);

        return {
            assistantId: String(response?.data?.id ?? assistantId),
            uuid: readAttachmentUuid(response, file.name),
        };
    } catch (err) {
        throw logApiError("uploadAssistantAttachment", err, { assistantId, file: file.name });
    }
}

/**
 * Upload every file in the queue to the assistant in parallel, invoking the
 * progress callback per file as each upload advances.
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
    const uploadTasks = files.map((file) =>
        uploadAssistantAttachment(
            assistantId,
            file,
            (progress) => onFileProgress?.(file, progress),
            signal,
        )
            .then((result): AttachmentQueueResult => ({ uuid: result.uuid }))
            .catch((error): AttachmentQueueResult => {
                onFileProgress?.(file, 0);
                return { error: ApiError.from(error) };
            }),
    );

    return Promise.all(uploadTasks);
}

/**
 * Delete an attachment from an assistant by its file id.
 */
export async function deleteAssistantAttachment(
    assistantId: string,
    fileId: string,
): Promise<void> {
    try {
        await useApp().restApi.deleteFromResourceAction(
            ASSISTANTS,
            `${assistantId}/actions/attachment`,
            { body: JSON.stringify({ fileId }) },
        );
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
