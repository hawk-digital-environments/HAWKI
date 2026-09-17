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

/**
 * Every packet type the `/req/streamAI` endpoint may emit. Single source of truth
 * for both the {@link AiStreamPacket} union and the runtime whitelist in `AiApi`.
 */
export const aiStreamPacketTypes = [
    'header', 'message', 'citation', 'status', 'completion', 'error',
    'reasoning_start', 'reasoning_delta', 'reasoning_end',
    'provider_tool_event', 'tool_call', 'tool_result'
] as const;

export type AiStreamPacketType = typeof aiStreamPacketTypes[number];

export type AiStreamPacket = AiStreamPacketBase & {
    type: AiStreamPacketType;
    content?: unknown;
    /** Token usage of the response; only present on the `completion` packet. */
    usage?: AiStreamUsage | null;
};

/**
 * Unified thinking-event payloads forwarded by the backend from the Laravel AI
 * package's stream events. The shapes are provider-agnostic for reasoning; the
 * `provider_tool_event` payload keeps the provider's raw item `type`/`data`
 * (e.g. OpenAI `web_search_call` vs Anthropic `server_tool_use`) and is mapped
 * client-side by the thinking reducer.
 */
export interface AiReasoningEvent {
    id: string;
    invocation_id: string | null;
    type: 'reasoning_start' | 'reasoning_delta' | 'reasoning_end';
    reasoning_id: string;
    /** Only present on `reasoning_delta`. */
    delta?: string;
    timestamp: number;
}

export interface AiProviderToolEvent {
    id: string;
    /** The provider's item type, e.g. OpenAI `web_search_call`, Anthropic `server_tool_use` / `web_search_tool_result`. */
    type: string;
    item_id: string;
    /** Raw provider payload; shape depends on {@link type}. */
    data: Record<string, unknown>;
    status: string;
    timestamp: number;
}

export interface AiToolCallEvent {
    id: string;
    invocation_id: string | null;
    type: 'tool_call';
    tool_id: string | null;
    tool_name: string | null;
    arguments: Record<string, unknown> | null;
    reasoning_id: string | null;
    timestamp: number;
}

export interface AiToolResultEvent {
    id: string;
    invocation_id: string | null;
    type: 'tool_result';
    tool_id: string | null;
    tool_name: string | null;
    result: unknown;
    successful: boolean;
    error: string | null;
    denied: boolean;
    timestamp: number;
}

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
