export type AiMessageRole = 'system' | 'user' | 'assistant' | 'tool' | (string & Record<never, never>);

export interface AiMessageContent {
    text?: string | null;
    attachments?: unknown[] | null;
}

export interface AiMessage {
    role: AiMessageRole;
    content: AiMessageContent;
}

export type AiModelParameters = Record<string, unknown> | unknown[];

/**
 * The feature-oriented input accepted by {@link AiApi}. The API adds the
 * legacy `/req/streamAI` envelope and its defaults before sending it.
 */
export interface AiStreamRequest {
    model: string;
    messages: AiMessage[];
    tools?: string[] | null;
    params?: AiModelParameters | null;
    /** Assistant handle (without `@`) the exchange is bound to; omitted for plain runs. */
    assistantHandle?: string | null;
    threadIndex?: number;
    slug?: string;
    isUpdate?: boolean;
    messageId?: string | null;
    key?: string;
}

export interface AiStatus {
    key?: string;
    value?: unknown;
    [key: string]: unknown;
}

interface AiStreamPacketBase {
    isDone?: boolean;
    status?: AiStatus | string | null;
    [key: string]: unknown;
}

export type AiStreamPacket = AiStreamPacketBase & {
    type: 'header' | 'message' | 'citation' | 'status' | 'completion' | 'error';
    content?: unknown;
};

export interface AiStreamResult {
    text: string;
    citations: unknown[];
    completed: boolean;
}

export interface AiRequestOptions {
    signal?: AbortSignal;
    headers?: HeadersInit;
}
