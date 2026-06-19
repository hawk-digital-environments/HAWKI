import {ToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
import type {FileAttachmentIssue} from '$lib/components/chat/composer/contexts/aspects/AttachmentAspect.svelte.js';
import {__} from '$lib/utils/translator.js';

/**
 * Surface attachment issues to the user. Shared by every entry point that stages
 * files (file picker, drag-and-drop) so they all report incompatibilities the
 * same way.
 */
export function reportAttachmentIssues(toastContext: ToastContext, issues: FileAttachmentIssue[] | true): void {
    if (issues === true) return;

    issues.forEach(issue => {
        if (issue.type === 'file_too_large') {
            toastContext.error(__('chat.attachments.fileTooLarge', {name: issue.file.name, size: String(issue.file.size), maxSize: String(issue.maxSize)}));
        } else if (issue.type === 'unsupported_file_type') {
            toastContext.error(__('chat.attachments.unsupportedFileType', {name: issue.file.name, type: issue.file.type || 'unbekannt'}));
        }
    });
}
