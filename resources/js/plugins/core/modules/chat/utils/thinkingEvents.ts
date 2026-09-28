import type {AiProviderToolEvent, AiReasoningEvent, AiStreamPacket} from '$lib/kernel/ai/types.js';
import type {ReasoningPart} from '$plugins/core/modules/chat/types.js';

/**
 * State of the reasoning timeline while an assistant response streams in.
 *
 * Built exclusively from the unified thinking-event packets the backend forwards
 * from the Laravel AI package (`reasoning_start`, `reasoning_delta`,
 * `reasoning_end`, `provider_tool_event`). Provider dialects only surface inside
 * `provider_tool_event` payloads and are mapped here, so the timeline itself is
 * identical across providers.
 */
export interface ThinkingTimeline {
    /** The reasoning steps in the order they happened. */
    parts: ReasoningPart[];
    /** True while incoming reasoning deltas append to the last text part. */
    textSegmentOpen: boolean;
    /**
     * Anthropic announces a web search with a `server_tool_use` event and
     * delivers its sources with the matching `web_search_tool_result`, keyed
     * by the tool-use id.
     */
    pendingSearches: Record<string, {query: string | null}>;
}

export function emptyThinkingTimeline(): ThinkingTimeline {
    return {parts: [], textSegmentOpen: false, pendingSearches: {}};
}

/**
 * Folds one streamed thinking-event packet into the timeline. Returns the
 * previous state when the packet does not affect it (empty deltas, tool
 * events without usable search information, …).
 */
export function applyThinkingEvent(timeline: ThinkingTimeline, packet: AiStreamPacket): ThinkingTimeline {
    switch (packet.type) {
        case 'reasoning_start':
            return {...timeline, textSegmentOpen: false};
        case 'reasoning_delta': {
            const event = packet.content as AiReasoningEvent | undefined;
            const delta = typeof event?.delta === 'string' ? event.delta : '';
            if (!delta) return timeline;
            const last = timeline.parts.at(-1);
            const parts: ReasoningPart[] = timeline.textSegmentOpen && last?.type === 'text'
                ? [...timeline.parts.slice(0, -1), {type: 'text', text: last.text + delta}]
                : [...timeline.parts, {type: 'text', text: delta}];
            return {...timeline, parts, textSegmentOpen: true};
        }
        case 'reasoning_end':
            return {...timeline, textSegmentOpen: false};
        case 'provider_tool_event':
            return applyProviderToolEvent(timeline, packet.content as AiProviderToolEvent | undefined);
        default:
            return timeline;
    }
}

function applyProviderToolEvent(timeline: ThinkingTimeline, event: AiProviderToolEvent | undefined): ThinkingTimeline
{
    if (!event) return timeline;

    // Any tool execution interleaves the reasoning, so the open text segment ends here.
    const timeline1 = {...timeline, textSegmentOpen: false};

    // OpenAI native web search: the completed `web_search_call` item carries the action.
    if (event.type === 'web_search_call' && event.status === 'completed') {
        const action = isRecord(event.data?.action) ? event.data.action : {};
        const actionType = isString(action.type) ? action.type : 'search';
        const query = isString(action.query) ? action.query : null;
        const fallbackUrl = isString(action.url) ? action.url : null;
        const sources = normaliseSources(action.sources, fallbackUrl);
        if (sources.length === 0 && (query === null || query === '')) return timeline1;
        return appendSearch(timeline1, actionType, query, sources);
    }

    // Anthropic native web search, announcement half. The block is emitted twice,
    // first as `started` with an empty input and then as `completed` once the
    // input JSON has streamed in, so only the completed event carries the query.
    if (event.type === 'server_tool_use' && event.status === 'completed' && event.data?.name === 'web_search') {
        const input = isRecord(event.data.input) ? event.data.input : {};
        const query = isString(input.query) ? input.query : null;
        return {
            ...timeline1,
            pendingSearches: {...timeline1.pendingSearches, [event.item_id]: {query}}
        };
    }

    // Anthropic native web search, result half; correlates via the tool-use id.
    if (event.type === 'web_search_tool_result') {
        const {[event.item_id]: pending = {query: null}, ...remaining} = timeline1.pendingSearches;
        const sources = normaliseSources(event.data?.content, null);
        return appendSearch({...timeline1, pendingSearches: remaining}, 'search', pending.query, sources);
    }

    return timeline1;
}

function appendSearch(timeline: ThinkingTimeline, action: string, query: string | null, sources: string[]): ThinkingTimeline
{
    return {
        ...timeline,
        parts: [...timeline.parts, {type: 'web_search', action, query, sources}]
    };
}

/**
 * Extracts source URLs from a provider's raw source list: entries are either
 * plain URL strings, `{url}` objects (OpenAI) or search-result blocks
 * (Anthropic). Duplicates are removed; `fallbackUrl` is used when the list
 * yields nothing.
 */
function normaliseSources(raw: unknown, fallbackUrl: string | null): string[] {
    const urls: string[] = [];
    if (Array.isArray(raw)) {
        for (const entry of raw) {
            if (isString(entry)) urls.push(entry);
            else if (isRecord(entry) && isString(entry.url)) urls.push(entry.url);
        }
    }
    const unique = [...new Set(urls)];
    return unique.length > 0 ? unique : (fallbackUrl !== null ? [fallbackUrl] : []);
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isString(value: unknown): value is string {
    return typeof value === 'string';
}
