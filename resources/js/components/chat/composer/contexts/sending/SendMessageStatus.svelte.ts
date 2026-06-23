import type {ResponseReader} from '$lib/components/chat/composer/contexts/sending/SendMessageResponse.svelte.js';

/**
 * Reactive state machine for a single in-flight send operation.
 *
 * Lifecycle: `sending` → `responding` → `received` (or `failed` at any point).
 *
 * - `sending` — the request is being transmitted.
 * - `responding` — the request succeeded; we are waiting for the full
 *   response body (which may be a stream of chunks).
 * - `received` — the complete response body has arrived.
 * - `failed` — a send or file error occurred.
 *
 * The `response` promise resolves to a `ResponseReader` as soon as the
 * transport acknowledges the send. Await `response`, then `response.body`
 * to know when the full content is available.
 */
export class SendMessageStatus {
    constructor(
        assignedFileUuids: Array<[File, string]>,
        /** Resolves to a `ResponseReader` once the message has been accepted by
         * the transport. The response body may still be streaming at that point. */
        public readonly response: Promise<ResponseReader>
    ) {
        this._fileUuids = $state(assignedFileUuids);

        this.response.then((res) => {
            this._awaitedResponse = res;
            // Update the status to "responding" once the message has been sent, but only if we are not already in a failed state (e.g. due to file issues or send issues)
            if (this._status === 'sending') {
                // Responding is true until the response body promise resolves, which indicates that the full response has been received.
                // If the sender set a non-stream response, the body promise will already be resolved, so we will transition to "done" immediately.
                this._status = 'responding';
                res.body.then(() => {
                    // Reach the final state once the full response has been received
                    this._status = 'received';
                });
            }
        });
    }

    private _status = $state<'sending' | 'failed' | 'responding' | 'received'>('sending');
    private _fileIssues = $state([] as Array<[File, string]>);
    private _awaitedResponse = $state(null as ResponseReader | null);
    private fileProgress = $state([] as Array<[File, number]>);
    private readonly _fileUuids: Array<[File, string]>;
    private _sendIssues = $state([] as string[]);
    private fileIssueMap = $derived(new Map<File, string>(this._fileIssues));
    private fileProgressMap = $derived(new Map<File, number>(this.fileProgress));
    private fileUuidMap = $derived.by(() => new Map<File, string>(this._fileUuids));

    /** Per-file upload errors reported by the transport (e.g. file too large on the server). */
    public readonly fileIssues = $derived.by(() => [...this._fileIssues]);
    public readonly fileUuids = $derived.by(() => [...this._fileUuids]);
    /** Non-file send errors (e.g. network failure, transport rejection). */
    public readonly sendIssues = $derived.by(() => [...this._sendIssues]);
    public readonly hasFileIssues = $derived.by(() => this.fileIssues.length > 0);
    public readonly hasSendIssues = $derived.by(() => this._sendIssues.length > 0);
    public readonly hasIssues = $derived.by(() => this.hasFileIssues || this.hasSendIssues);
    /** Whether the in-flight request can be cancelled (requires the transport to register an `AbortController`). */
    public readonly canBeAborted = $derived.by(() => this._awaitedResponse?.canAbort ?? false);

    /** Raw status string. Prefer the boolean shorthands below for most UI logic. */
    public readonly status = $derived.by(() => this._status);
    public readonly sending = $derived.by(() => this._status === 'sending');
    public readonly failed = $derived.by(() => this._status === 'failed');
    public readonly responding = $derived.by(() => this._status === 'responding');
    public readonly received = $derived.by(() => this._status === 'received');
    /** `true` once the send has reached a final state (`received` or `failed`). */
    public readonly done = $derived.by(() => this._status === 'received' || this._status === 'failed');
    /** `true` while a request is in flight (`sending` or `responding`). Use to disable the send button. */
    public readonly active = $derived.by(() => this._status === 'sending' || this._status === 'responding');

    public hasFileIssue(file: File): boolean {
        return this.fileIssueMap.has(file);
    }

    public getFileIssue(file: File): string | null {
        return this.fileIssueMap.get(file) ?? null;
    }

    /** Records an upload error for a specific file and sets the overall status to `'failed'`. */
    public addFileIssue(file: File, issue: string): void {
        this._status = 'failed';
        this.fileIssues.push([file, issue]);
    }

    public clearFileIssue(file: File): void {
        this._fileIssues = this.fileIssues.filter(([f]) => f !== file);
    }

    /** Records a general send error and sets the overall status to `'failed'`. */
    public addSendIssue(issue: string): void {
        this._status = 'failed';
        this._sendIssues.push(issue);
    }

    /** Returns the upload progress (0–1) for a file, or `null` if not yet reported. */
    public getFileProgress(file: File): number | null {
        return this.fileProgressMap.get(file) ?? null;
    }

    /** Updates the upload progress (0–1) for a file. Called by the transport as uploads proceed. */
    public setFileProgress(file: File, progress: number): void {
        const existingIndex = this.fileProgress.findIndex(([f]) => f === file);
        if (existingIndex !== -1) {
            this.fileProgress[existingIndex][1] = progress;
        } else {
            this.fileProgress.push([file, progress]);
        }
    }

    public getFileUuid(file: File): string | null {
        return this.fileUuidMap.get(file) ?? null;
    }

    public hasFileUuid(file: File): boolean {
        return this.fileUuidMap.has(file);
    }

    /** Records the server-assigned UUID for an uploaded file.
     *  Called by the transport once a file upload resolves. */
    public setFileUuid(file: File, uuid: string): void {
        const existingIndex = this._fileUuids.findIndex(([f]) => f === file);
        if (existingIndex !== -1) {
            this._fileUuids[existingIndex][1] = uuid;
        } else {
            this._fileUuids.push([file, uuid]);
        }
    }
}
