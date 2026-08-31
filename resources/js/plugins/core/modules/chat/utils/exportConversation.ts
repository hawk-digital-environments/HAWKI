import type {ChatConversation} from '$plugins/core/modules/chat/types.js';

export type ConversationExportFormat = 'print' | 'pdf' | 'word' | 'json' | 'csv';

/**
 * Localized section labels for the exported document.
 *
 * Declared as an explicit shape (not the open `Record<string, string>` a
 * flattened translation subtree has) so every label read in this module is
 * checked by the compiler. Values are guaranteed to be strings, but may be
 * empty when a translation is missing — see {@link toConversationExportLabels}.
 */
export type ConversationExportLabels = {
    systemPrompt: string;
    conversation: string;
    attachments: string;
};

/**
 * Picks the labels the export needs out of a flattened `chat.export` translation
 * subtree, i.e. `useTranslator().getTranslationsFlat('chat.export')`.
 *
 * That subtree holds more entries than the export renders (menu title, format
 * names, error text) and is typed as an open record, so the keys are narrowed
 * here instead of at every read. Entries that are absent — the label set is
 * still loading, or a key was renamed — become empty strings rather than
 * `undefined` leaking into the document.
 */
export function toConversationExportLabels(labels: Record<string, string>): ConversationExportLabels {
    return {
        systemPrompt: labels.systemPrompt ?? '',
        conversation: labels.conversation ?? '',
        attachments: labels.attachments ?? ''
    };
}

type ExportMessage = {
    id: string;
    role: 'user' | 'assistant';
    author: string;
    model: string | null;
    message: string;
    created_at: string;
    attachments: Array<{name: string; mime: string; url: string}>;
};

/**
 * Exports the current decrypted conversation without reading the rendered DOM.
 *
 * @todo This is the weakest port of the legacy export (`public/js/export.js`) and
 *   one of the most important features for academic use. Still missing compared
 *   to the old implementation:
 *   - AI-generated summary of the conversation (PDF/Word header section)
 *   - markdown rendering in Word (headings, lists, code) instead of plain text
 *   - inline attachment previews/images
 *   Tracked in Kanban card `maqorg6zuyim` — "Chat export: reach feature parity
 *   with legacy export" on the "HAWKI Frontend Rewrite" board (KI workspace).
 */
export async function exportConversation(
    conversation: ChatConversation,
    format: ConversationExportFormat,
    labels: ConversationExportLabels
): Promise<void> {
    if (format === 'print') {
        window.print();
        return;
    }

    const messages = toExportMessages(conversation);
    const filename = safeFilename(conversation.name);

    if (format === 'json') {
        download(`${filename}.json`, JSON.stringify({
            name: conversation.name,
            system_prompt: conversation.system_prompt,
            messages
        }, null, 2), 'application/json');
        return;
    }

    if (format === 'csv') {
        download(`${filename}.csv`, toCsv(messages), 'text/csv;charset=utf-8');
        return;
    }

    if (format === 'pdf') {
        await exportPdf(conversation, messages, filename, labels);
        return;
    }

    await exportWord(conversation, messages, filename, labels);
}

function toExportMessages(conversation: ChatConversation): ExportMessage[] {
    return conversation.messages.map(message => ({
        id: message.message_id,
        role: message.message_role,
        author: message.author.name,
        model: message.model,
        message: message.content.text,
        created_at: message.created_at,
        attachments: (message.content.attachments ?? []).map(({fileData}) => ({
            name: fileData.name,
            mime: fileData.mime,
            url: fileData.url
        }))
    }));
}

function toCsv(messages: ExportMessage[]): string {
    const quote = (value: unknown) => {
        const text = String(value ?? '');
        const spreadsheetSafeText = /^[=+\-@]/.test(text) ? `'${text}` : text;
        return `"${spreadsheetSafeText.replaceAll('"', '""')}"`;
    };
    const columns = ['id', 'role', 'author', 'model', 'message', 'created_at', 'attachments'] as const;
    const rows = messages.map(message => ({
        ...message,
        attachments: message.attachments.map(attachment => attachment.name).join('; ')
    }));

    return [
        columns.map(quote).join(','),
        ...rows.map(row => columns.map(column => quote(row[column])).join(','))
    ].join('\n');
}

async function exportPdf(
    conversation: ChatConversation,
    messages: ExportMessage[],
    filename: string,
    labels: ConversationExportLabels
): Promise<void> {
    const {jsPDF: JsPdf} = await import('jspdf');
    const pdf = new JsPdf();
    const content = document.createElement('article');
    content.style.cssText = [
        'width: 794px',
        'padding: 48px',
        'box-sizing: border-box',
        'background: white',
        'color: black',
        'font: 16px/1.5 Arial, "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif'
    ].join(';');
    const existingOverlays = new Set(document.querySelectorAll('.html2pdf__overlay'));

    appendPdfText(content, 'h1', conversation.name);
    if (conversation.system_prompt) {
        appendPdfText(content, 'h2', labels.systemPrompt);
        appendPdfText(content, 'p', conversation.system_prompt);
    }
    appendPdfText(content, 'h2', labels.conversation);
    for (const message of messages) {
        const heading = message.model ? `${message.author} (${message.model})` : message.author;
        const section = document.createElement('section');
        section.style.cssText = 'break-inside: avoid-page; margin: 0 0 20px';
        appendPdfText(section, 'h3', heading);
        appendPdfText(section, 'p', message.message);
        if (message.attachments.length > 0) {
            const attachments = appendPdfText(
                section,
                'p',
                `${labels.attachments}: ${message.attachments.map(attachment => attachment.name).join(', ')}`
            );
            attachments.style.fontStyle = 'italic';
        }
        content.append(section);
    }

    try {
        await pdf.html(content, {
            autoPaging: 'text',
            html2canvas: {
                backgroundColor: '#ffffff',
                scale: 1,
                useCORS: true,
                onclone: clonedDocument => {
                    clonedDocument.querySelectorAll('link[rel="stylesheet"], style').forEach(stylesheet => stylesheet.remove());
                    clonedDocument.documentElement.style.backgroundColor = '#ffffff';
                    clonedDocument.body.style.backgroundColor = '#ffffff';
                }
            },
            margin: [10, 10, 10, 10],
            width: 190,
            windowWidth: 794
        });
        pdf.save(`${filename}.pdf`);
    } finally {
        content.remove();
        document.querySelectorAll('.html2pdf__overlay').forEach(overlay => {
            if (!existingOverlays.has(overlay)) overlay.remove();
        });
    }
}

function appendPdfText(parent: HTMLElement, tag: 'h1' | 'h2' | 'h3' | 'p', text: string): HTMLElement {
    const element = document.createElement(tag);
    element.textContent = text || ' ';
    element.style.whiteSpace = 'pre-wrap';
    element.style.overflowWrap = 'anywhere';
    parent.append(element);
    return element;
}

async function exportWord(
    conversation: ChatConversation,
    messages: ExportMessage[],
    filename: string,
    labels: ConversationExportLabels
): Promise<void> {
    const {Document, HeadingLevel, Packer, Paragraph, TextRun} = await import('docx');
    const children = [
        new Paragraph({text: conversation.name, heading: HeadingLevel.TITLE}),
        ...(conversation.system_prompt ? [
            new Paragraph({text: labels.systemPrompt, heading: HeadingLevel.HEADING_1}),
            new Paragraph(conversation.system_prompt)
        ] : []),
        new Paragraph({text: labels.conversation, heading: HeadingLevel.HEADING_1})
    ];

    for (const message of messages) {
        children.push(new Paragraph({
            children: [new TextRun({
                text: message.model ? `${message.author} (${message.model})` : message.author,
                bold: true
            })],
            spacing: {before: 240, after: 80}
        }));
        for (const line of message.message.split('\n')) {
            children.push(new Paragraph(line));
        }
        if (message.attachments.length > 0) {
            children.push(new Paragraph({
                children: [new TextRun({
                    text: `${labels.attachments}: ${message.attachments.map(attachment => attachment.name).join(', ')}`,
                    italics: true
                })]
            }));
        }
    }

    const document = new Document({sections: [{children}]});
    downloadBlob(`${filename}.docx`, await Packer.toBlob(document));
}

function download(filename: string, content: string, type: string): void {
    downloadBlob(filename, new Blob([content], {type}));
}

function downloadBlob(filename: string, blob: Blob): void {
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.download = filename;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url));
}

function safeFilename(value: string): string {
    return value.replace(/[\\/:*?"<>|]/g, '-').trim() || 'conversation';
}
