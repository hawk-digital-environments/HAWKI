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
    /** Token usage of the response; only present on the `completion` packet. */
    usage?: AiStreamUsage | null;
};

export interface AiStreamUsage {
    model?: string;
    prompt_tokens?: number;
    completion_tokens?: number;
}

export interface AiStreamResult {
    text: string;
    citations: unknown[];
    completed: boolean;
}

export interface AiRequestOptions {
    signal?: AbortSignal;
    headers?: HeadersInit;
}
