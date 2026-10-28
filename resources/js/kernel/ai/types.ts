import type z from 'zod';
import type {UrlCitation} from '$lib/components/ui/citations/types.js';
import type {
    AiProviderToolEventSchema,
    AiReasoningDeltaEventSchema,
    AiReasoningEndEventSchema,
    AiReasoningStartEventSchema,
    AiStatusSchema,
    AiStreamPacketSchema,
    AiStreamUsageSchema,
    AiToolCallEventSchema,
    AiToolResultEventSchema
} from '$lib/kernel/ai/streamPacket.schema.js';

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

export type AiStatus = z.infer<typeof AiStatusSchema>;
export type AiStreamUsage = z.infer<typeof AiStreamUsageSchema>;

/** Discriminated union of all `/req/streamAI` packets; narrow on `type` to get the typed `content`. */
export type AiStreamPacket = z.infer<typeof AiStreamPacketSchema>;
export type AiStreamPacketType = AiStreamPacket['type'];
/** A single packet of the given type, with its `content` typed accordingly. */
export type AiStreamPacketOf<T extends AiStreamPacketType> = Extract<AiStreamPacket, {type: T}>;

/**
 * Unified thinking-event payloads forwarded by the backend from the Laravel AI
 * package's stream events; see `streamPacket.schema.ts`.
 */
export type AiReasoningStartEvent = z.infer<typeof AiReasoningStartEventSchema>;
export type AiReasoningDeltaEvent = z.infer<typeof AiReasoningDeltaEventSchema>;
export type AiReasoningEndEvent = z.infer<typeof AiReasoningEndEventSchema>;
export type AiProviderToolEvent = z.infer<typeof AiProviderToolEventSchema>;
export type AiToolCallEvent = z.infer<typeof AiToolCallEventSchema>;
export type AiToolResultEvent = z.infer<typeof AiToolResultEventSchema>;

export interface AiStreamResult {
    text: string;
    citations: UrlCitation[];
    completed: boolean;
}

export interface AiRequestOptions {
    signal?: AbortSignal;
    headers?: HeadersInit;
}
