import {getConfig} from '$lib/data/config/config.js';
import type {CheckpointingInterface} from '$lib/components/chat/composer/contexts/utils/CheckpointingInterface.js';

/**
 * Describes an issue encountered when trying to add a file attachment, such as unsupported type or excessive size.
 */
export interface FileAttachmentIssue {
    type: 'file_too_large' | 'unsupported_file_type';
    file: File;
    maxSize?: number;
}

interface AttachmentAspectCheckpoint {
    list: File[];
    uuids: Array<[File, string]>;
}

export class AttachmentAspect implements CheckpointingInterface<AttachmentAspectCheckpoint> {
    /** Files staged for the next message. Managed via {@link add} / {@link remove}. */
    private _list = $state<File[]>([]);

    /** Maps files to their assigned UUIDs after upload, for correlating files with server responses. Cleared on successful send. */
    private _assignedUuids = $state([] as Array<[File, string]>);

    public list = $derived.by(() => [...this._list]);
    public assignedUuids = $derived.by(() => [...(this._assignedUuids.map(a => [a[0], a[1]] as [File, string]))]);

    /** MIME types permitted by server config. Use for the `accept` attribute on file inputs. */
    public allowedMimeTypes = $derived.by(() => getConfig().storage_files?.allowedMimeTypes ?? []);

    /** File extensions permitted by server config (e.g. `['pdf', 'png']`). */
    public allowedExtensions = $derived.by(() => getConfig().storage_files?.allowedExtensions ?? []);

    /** `true` when at least one file is staged. */
    public hasAny = $derived.by(() => this._list.length > 0);

    /** `true` when at least one staged file is an image. Affects model usability checks. */
    public hasImages = $derived.by(() => this._list.some(file => file.type.startsWith('image/')));

    /**
     * Appends one file or all files from a `FileList` to the attachment list.
     * Every supported file from the batch is added; unsupported or oversized
     * files are skipped and reported.
     * @returns `true` if all files were added; otherwise the list of issues for
     *   the files that were skipped (the supported ones are still added).
     */
    public add(file: File | FileList): FileAttachmentIssue[] | true {
        const filesToAdd = file instanceof FileList ? Array.from(file) : [file];
        const filesToAddFiltered = [];
        const issues: FileAttachmentIssue[] = [];
        const maxSize = getConfig().storage_files?.maxFileSize ?? Infinity;
        for (const f of filesToAdd) {
            if (this.allowedMimeTypes.length > 0 && !this.allowedMimeTypes.includes(f.type)) {
                issues.push({type: 'unsupported_file_type', file: f});
            } else if (f.size > maxSize) {
                issues.push({type: 'file_too_large', file: f, maxSize});
            } else {
                filesToAddFiltered.push(f);
            }
        }
        if (filesToAddFiltered.length > 0) {
            this._list = [...this._list, ...filesToAddFiltered];
        }
        return issues.length > 0 ? issues : true;
    }

    /** Removes an attachment by reference */
    public remove(file: File): void {
        this._list = this._list.filter((f: File) => f !== file);
        this._assignedUuids = this._assignedUuids.filter(([f]) => f !== file);
    }

    public clear(): void {
        this._list = [];
        this._assignedUuids = [];
    }

    public assignUuid(file: File, uuid: string): void {
        const filteredUuids = this._assignedUuids.filter(([f]) => f !== file);
        this._assignedUuids = [...filteredUuids, [file, uuid]];
    }

    public getAssignedUuid(file: File): string | null {
        return this._assignedUuids.find(([f]) => f === file)?.[1] ?? null;
    }

    public createCheckpoint(): AttachmentAspectCheckpoint {
        return {
            list: [...this._list],
            uuids: [...this._assignedUuids]
        };
    }

    public restoreCheckpoint(checkpoint: AttachmentAspectCheckpoint): void {
        this._list = [...checkpoint.list];
    }
}
