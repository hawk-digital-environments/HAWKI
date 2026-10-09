import z from 'zod';
import type {UrlCitation} from '$lib/components/ui/citations/types.js';

/**
 * Zod schemas for the NDJSON packets emitted by the `/req/streamAI` endpoint
 * (see `StreamController::handleStreamingRequest`). `AiApi` parses every
 * packet with {@link AiStreamPacketSchema}, so consumers can narrow on `type`
 * and get a typed `content`.
 *
 * Objects are loose on purpose: the backend and the Laravel AI package may add
 * fields, which must not break the stream.
 */

export const AiStatusSchema = z.looseObject({
    key: z.string().optional(),
    value: z.unknown().optional()
});

export const AiStreamUsageSchema = z.looseObject({
    model: z.string().optional(),
    prompt_tokens: z.number().optional(),
    completion_tokens: z.number().optional()
});

/**
 * A cited web source. Laravel AI's stock `UrlCitation` only carries
 * `start_index`/`end_index`; HAWKI's `UrlMultiCitation` adds `ranges` and
 * `byteOffset`. Missing `ranges` default to an empty list.
 */
export const AiUrlCitationSchema = z.looseObject({
    url: z.string(),
    title: z.string().nullable(),
    ranges: z.array(z.tuple([z.number(), z.number()])).default([]),
    byteOffset: z.boolean().optional()
}) satisfies z.ZodType<UrlCitation>;

const AiReasoningEventBaseSchema = z.looseObject({
    id: z.string(),
    invocation_id: z.string().nullable(),
    /** Identifies the reasoning block; all events of one block share it. */
    reasoning_id: z.string(),
    timestamp: z.number()
});

export const AiReasoningStartEventSchema = AiReasoningEventBaseSchema.extend({
    type: z.literal('reasoning_start')
});

export const AiReasoningDeltaEventSchema = AiReasoningEventBaseSchema.extend({
    type: z.literal('reasoning_delta'),
    delta: z.string(),
    summary: z.unknown().optional()
});

export const AiReasoningEndEventSchema = AiReasoningEventBaseSchema.extend({
    type: z.literal('reasoning_end'),
    summary: z.unknown().optional()
});

/**
 * Keeps the provider's raw item `type`/`data` (e.g. OpenAI `web_search_call`
 * vs Anthropic `server_tool_use`); mapped client-side by the thinking reducer.
 */
export const AiProviderToolEventSchema = z.looseObject({
    id: z.string(),
    /** The provider's item type, e.g. OpenAI `web_search_call`, Anthropic `server_tool_use` / `web_search_tool_result`. */
    type: z.string(),
    item_id: z.string(),
    /** Raw provider item; shape depends on {@link type}. */
    data: z.record(z.string(), z.unknown()),
    status: z.string(),
    timestamp: z.number(),
    provider: z.string().nullable().optional()
});

export const AiToolCallEventSchema = z.looseObject({
    id: z.string(),
    invocation_id: z.string().nullable(),
    type: z.literal('tool_call'),
    tool_id: z.string().nullable(),
    tool_name: z.string().nullable(),
    /** A tool without parameters is sent as an empty list. */
    arguments: z.union([z.record(z.string(), z.unknown()), z.tuple([])]).nullable(),
    reasoning_id: z.string().nullable(),
    timestamp: z.number()
});

export const AiToolResultEventSchema = z.looseObject({
    id: z.string(),
    invocation_id: z.string().nullable(),
    type: z.literal('tool_result'),
    tool_id: z.string().nullable(),
    tool_name: z.string().nullable(),
    result: z.unknown(),
    successful: z.boolean(),
    error: z.string().nullable(),
    denied: z.boolean(),
    timestamp: z.number()
});

const AiStreamPacketBaseSchema = z.looseObject({
    isDone: z.boolean().optional(),
    status: z.union([AiStatusSchema, z.string()]).nullable().optional()
});

function packet<const T extends string, C extends z.ZodType>(type: T, content: C) {
    return AiStreamPacketBaseSchema.extend({type: z.literal(type), content});
}

export const AiStreamPacketSchema = z.discriminatedUnion('type', [
    packet('header', z.string()).extend({
        author: z.looseObject({
            username: z.string(),
            name: z.string(),
            avatar_url: z.string().nullable()
        }),
        model: z.string(),
        tools: z.array(z.unknown())
    }),
    packet('message', z.string()),
    packet('citation', AiUrlCitationSchema),
    packet('status', z.unknown()),
    packet('completion', z.string()).extend({
        /** Token usage of the response. */
        usage: AiStreamUsageSchema.nullable().optional()
    }),
    packet('error', z.string()),
    packet('reasoning_start', AiReasoningStartEventSchema),
    packet('reasoning_delta', AiReasoningDeltaEventSchema),
    packet('reasoning_end', AiReasoningEndEventSchema),
    packet('provider_tool_event', AiProviderToolEventSchema),
    packet('tool_call', AiToolCallEventSchema),
    packet('tool_result', AiToolResultEventSchema)
]);
