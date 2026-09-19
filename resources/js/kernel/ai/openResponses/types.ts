/**
 * Wire types for the Open Responses API spoken by the HAWKI chat proxy
 * (`POST /api/hawki/v1/chat/{format?}`, default format `openResponses`).
 *
 * The event union below is the subset the frontend consumes; unknown event
 * types (including the `hawki:` extension events we do handle explicitly)
 * arrive as {@link OpenResponsesUnknownEvent} and are safe to ignore per spec.
 */

export interface ChatExchangeMessage {
    role: 'user' | 'assistant';
    text: string;
}

/** Feature-oriented request accepted by {@link OpenResponsesApi}. */
export interface ChatExchangeRequest {
    model: string;
    /** System instructions; sent as the Open Responses `instructions` field. */
    instructions?: string;
    /** Full conversation history in chronological order; the last message must be a user turn. */
    messages: ChatExchangeMessage[];
    /** HAWKI tool-transfer strings, sent via the `hawki` request extension. */
    tools?: string[] | null;
    /** HAWKI attachment UUIDs for the final user turn, sent via the `hawki` request extension. */
    attachments?: string[] | null;
    /** HAWKI model parameters (temp, top_p, max_tokens, …), sent via the `hawki` request extension. */
    params?: Record<string, unknown> | null;
}

export interface ChatRequestOptions {
    signal?: AbortSignal;
    headers?: HeadersInit;
}

export interface OpenResponsesCitation {
    url: string | null;
    title: string | null;
    startIndex: number | null;
    endIndex: number | null;
}

interface OpenResponsesEventBase {
    type: string;
    sequence_number: number;
}

export interface OpenResponsesResponseSnapshot {
    id: string;
    status: string;
    model: string;
    output: Array<Record<string, unknown>>;
    usage?: OpenResponsesUsage;
    error?: { code: string | null; message: string } | null;
}

export interface OpenResponsesUsage {
    input_tokens: number;
    output_tokens: number;
    total_tokens: number;
}

interface OutputItemAddedEvent extends OpenResponsesEventBase {
    type: 'response.output_item.added';
    output_index: number;
    item: {
        id: string;
        type: string;
        status?: string;
        name?: string;
        call_id?: string;
    };
}

interface TextDeltaEvent extends OpenResponsesEventBase {
    type: 'response.output_text.delta';
    item_id: string;
    delta: string;
}

interface ReasoningSummaryDeltaEvent extends OpenResponsesEventBase {
    type: 'response.reasoning_summary_text.delta';
    delta: string;
}

interface CitationEvent extends OpenResponsesEventBase {
    type: 'hawki:citation';
    citation: {
        url?: string | null;
        title?: string | null;
        start_index?: number | null;
        end_index?: number | null;
    };
}

interface ProviderToolEvent extends OpenResponsesEventBase {
    type: 'hawki:provider_tool_event';
    item_id: string;
    event_type: string;
    status: string;
}

interface TerminalEvent extends OpenResponsesEventBase {
    type: 'response.completed' | 'response.incomplete' | 'response.failed';
    response: OpenResponsesResponseSnapshot;
}

interface StreamErrorEvent extends OpenResponsesEventBase {
    type: 'error';
    error: {
        type?: string | null;
        code?: string | null;
        message: string;
        param?: string | null;
    };
}

export type OpenResponsesStreamEvent =
    | OutputItemAddedEvent
    | TextDeltaEvent
    | ReasoningSummaryDeltaEvent
    | CitationEvent
    | ProviderToolEvent
    | TerminalEvent
    | StreamErrorEvent
    | ResponseLifecycleEvent;

/**
 * Lifecycle events that carry a response snapshot but need no special
 * handling in most consumers (`response.created`, `response.in_progress`).
 */
interface ResponseLifecycleEvent extends OpenResponsesEventBase {
    type:
        | 'response.created'
        | 'response.in_progress'
        | 'response.output_item.done'
        | 'response.content_part.added'
        | 'response.content_part.done'
        | 'response.output_text.done'
        | 'response.reasoning_summary_part.added'
        | 'response.reasoning_summary_part.done'
        | 'response.reasoning_summary_text.done'
        | 'response.function_call_arguments.delta'
        | 'response.function_call_arguments.done';
    response?: OpenResponsesResponseSnapshot;
    [key: string]: unknown;
}

export interface ChatExchangeResult {
    text: string;
    citations: OpenResponsesCitation[];
    completed: boolean;
    status: string | null;
}
