import type {ChatConversation} from '$plugins/core/modules/chat/types.js';
import type {OldUiExportType} from '$lib/legacy/OldUiBridge.svelte.js';

/**
 * Exports an open conversation in the format chosen in the chat header's
 * export menu. `print`/`pdf` route through the browser's print dialog (the
 * conversation page carries matching `@media print` styles); the other
 * formats download a generated file named after the conversation.
 */
export function exportConversation(conversation: ChatConversation, format: OldUiExportType): void {
    if (format === 'print' || format === 'pdf') {
        window.print();
        return;
    }

    const rows = conversation.messages.map(message => ({
        role: message.message_role,
        author: message.author.name,
        model: message.model,
        message: message.content.text,
        created_at: message.created_at
    }));
    if (format === 'json') {
        download(`${conversation.name}.json`, JSON.stringify({
            name: conversation.name,
            system_prompt: conversation.system_prompt,
            messages: rows
        }, null, 2), 'application/json');
    } else if (format === 'csv') {
        const quote = (value: unknown) => {
            const text = String(value ?? '');
            const spreadsheetSafeText = /^[=+\-@]/.test(text) ? `'${text}` : text;
            return `"${spreadsheetSafeText.replaceAll('"', '""')}"`;
        };
        download(`${conversation.name}.csv`, [
            ['role', 'author', 'model', 'message', 'created_at'].map(quote).join(','),
            ...rows.map(row => Object.values(row).map(quote).join(','))
        ].join('\n'), 'text/csv');
    } else {
        const body = rows.map(row => `<h2>${escapeHtml(row.author)}</h2><p>${escapeHtml(row.message).replaceAll('\n', '<br>')}</p>`).join('');
        download(`${conversation.name}.doc`, `<html><body><h1>${escapeHtml(conversation.name)}</h1>${body}</body></html>`, 'application/msword');
    }
}

function download(filename: string, content: string, type: string): void {
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([content], {type}));
    link.download = filename.replace(/[\\/:*?"<>|]/g, '-');
    link.click();
    URL.revokeObjectURL(link.href);
}

function escapeHtml(value: string): string {
    const element = document.createElement('div');
    element.textContent = value;
    return element.innerHTML;
}
