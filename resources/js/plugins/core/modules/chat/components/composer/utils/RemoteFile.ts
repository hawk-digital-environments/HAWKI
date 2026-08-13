/**
 * Stand-in for a `File` that already exists on the server (no local bytes) — e.g.
 * `ChatEditMode` reconstructs a message's original attachments as `RemoteFile`s from
 * `att.fileData.url/name/mime` so they show up in `AttachmentSlice.list` alongside
 * newly-picked local files. Always constructed with an empty blob body; `previewUrl`
 * (not the blob) is what `FilePreview` renders as the thumbnail `src`.
 */
export class RemoteFile extends File {
    public readonly previewUrl: string;

    constructor(
        previewUrl: string,
        name: string,
        type: string
    ) {
        super([], name, {type});
        this.previewUrl = previewUrl;
    }
}
