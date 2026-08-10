import {ToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {FileAttachmentIssue} from '$plugins/core/modules/chat/components/composer/contexts/slices/AttachmentSlice.svelte.js';

/**
 * Surface attachment issues to the user. Shared by every entry point that stages
 * files (file picker, drag-and-drop) so they all report incompatibilities the
 * same way.
 */
export function reportAttachmentIssues(
    translator: Translator,
    toastContext: ToastContext,
    issues: FileAttachmentIssue[] | true
): void {
    if (issues === true) return;

    issues.forEach(issue => {
        if (issue.type === 'file_too_large') {
            toastContext.error(translator.translate('chat.attachments.fileTooLarge', {name: issue.file.name, size: String(issue.file.size), maxSize: String(issue.maxSize)}));
        } else if (issue.type === 'unsupported_file_type') {
            toastContext.error(translator.translate('chat.attachments.unsupportedFileType', {name: issue.file.name, type: issue.file.type || 'unbekannt'}));
        }
    });
}
