import type {ChatConversation} from '$plugins/core/modules/chat/types.js';

export type ConversationExportFormat = 'print' | 'pdf' | 'word' | 'json' | 'csv';

export type ConversationExportLabels = {
    systemPrompt: string;
    conversation: string;
    attachments: string;
};

type ExportMessage = {
    id: string;
    role: 'user' | 'assistant';
    author: string;
    model: string | null;
    message: string;
    created_at: string;
    attachments: Array<{name: string; mime: string; url: string}>;
};

/** Exports the current decrypted conversation without reading the rendered DOM. */
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
    const {default: JsPdf} = await import('jspdf');
    const document = new JsPdf();
    const margin = 20;
    const pageWidth = document.internal.pageSize.getWidth();
    const pageHeight = document.internal.pageSize.getHeight();
    const contentWidth = pageWidth - margin * 2;
    let y = margin;

    const write = (text: string, fontSize = 11, bold = false, gap = 5) => {
        document.setFont('helvetica', bold ? 'bold' : 'normal');
        document.setFontSize(fontSize);
        const lines = document.splitTextToSize(text || ' ', contentWidth) as string[];
        const lineHeight = fontSize * 0.45;
        for (const line of lines) {
            if (y + lineHeight > pageHeight - margin) {
                document.addPage();
                y = margin;
            }
            document.text(line, margin, y);
            y += lineHeight;
        }
        y += gap;
    };

    write(conversation.name, 18, true, 8);
    if (conversation.system_prompt) {
        write(labels.systemPrompt, 13, true, 3);
        write(conversation.system_prompt, 10, false, 8);
    }
    for (const message of messages) {
        const heading = message.model ? `${message.author} (${message.model})` : message.author;
        write(heading, 12, true, 2);
        write(message.message, 10, false, 2);
        if (message.attachments.length > 0) {
            write(`${labels.attachments}: ${message.attachments.map(attachment => attachment.name).join(', ')}`, 9, false, 6);
        }
    }

    document.save(`${filename}.pdf`);
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
