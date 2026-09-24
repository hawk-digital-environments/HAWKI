import type {AiProviderToolEvent, AiReasoningDeltaEvent, AiStreamPacket} from '$lib/kernel/ai/types.js';
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
    /**
     * Index into {@link parts} of the text part each reasoning block writes to,
     * keyed by the block's `reasoning_id`. Every id gets its own part, so
     * separate reasoning blocks never merge even when nothing sits between them.
     * A block with several titled sections gets one part per section; the index
     * then points at the section currently streaming in.
     */
    textPartIndex: Record<string, number>;
    /**
     * Anthropic announces a web search with a `server_tool_use` event and
     * delivers its sources with the matching `web_search_tool_result`, keyed
     * by the tool-use id.
     */
    pendingSearches: Record<string, { query: string | null }>;
}

/**
 * Bold text ending its line — how models title a section of their reasoning
 * ("**Title**\n\nBody"). A title starts its line or is glued to the text before
 * it: OpenAI's summary parts carry no trailing newline, so consecutive sections
 * of one block arrive as "…done.**Next title**". Inline bold after a space is
 * not a title. The newline must already be there, so bold text that is still
 * streaming in is never mistaken for one.
 */
const SECTION_TITLE = /(?:^[ \t]*|(?<=\S))\*\*[^*\n]+\*\*[ \t]*\n/gm;

export function emptyThinkingTimeline(): ThinkingTimeline {
    return {parts: [], textPartIndex: {}, pendingSearches: {}};
}

/**
 * Folds one streamed thinking-event packet into the timeline. Returns the
 * previous state when the packet does not affect it (empty deltas, tool
 * events without usable search information, …).
 */
export function applyThinkingEvent(timeline: ThinkingTimeline, packet: AiStreamPacket): ThinkingTimeline {
    switch (packet.type) {
        case 'reasoning_delta':
            return applyReasoningDelta(timeline, packet.content);
        case 'provider_tool_event':
            return applyProviderToolEvent(timeline, packet.content);
        default:
            return timeline;
    }
}

function applyReasoningDelta(timeline: ThinkingTimeline, event: AiReasoningDeltaEvent): ThinkingTimeline
{
    const {reasoning_id: reasoningId, delta} = event;
    if (!delta) return timeline;

    const known = timeline.textPartIndex[reasoningId];
    const existing = known === undefined ? undefined : timeline.parts[known];
    const isOpen = known !== undefined && existing?.type === 'text';
    const index = isOpen ? known : timeline.parts.length;
    const sections = splitSections((isOpen ? existing.text : '') + delta);

    const parts = [...timeline.parts];
    parts.splice(index, isOpen ? 1 : 0, ...sections.map(text => ({type: 'text' as const, text})));

    // Sections inserted mid-list push the parts of later blocks back.
    const shift = sections.length - (isOpen ? 1 : 0);
    const textPartIndex: Record<string, number> = {};
    for (const [id, partIndex] of Object.entries(timeline.textPartIndex)) {
        textPartIndex[id] = partIndex > index ? partIndex + shift : partIndex;
    }
    textPartIndex[reasoningId] = index + sections.length - 1;

    return {...timeline, parts, textPartIndex};
}

/**
 * Cuts reasoning text before every {@link SECTION_TITLE} that has text in
 * front of it, so each titled section becomes its own part. A title opening
 * the text stays with it.
 */
function splitSections(text: string): string[] {
    const sections: string[] = [];
    let start = 0;
    for (const match of text.matchAll(SECTION_TITLE)) {
        if (text.slice(start, match.index).trim() === '') continue;
        sections.push(text.slice(start, match.index));
        start = match.index;
    }
    sections.push(text.slice(start));
    return sections;
}

function applyProviderToolEvent(timeline: ThinkingTimeline, event: AiProviderToolEvent): ThinkingTimeline
{
    // OpenAI native web search: the completed `web_search_call` item carries the action.
    if (event.type === 'web_search_call' && event.status === 'completed') {
        const action = isRecord(event.data?.action) ? event.data.action : {};
        const actionType = isString(action.type) ? action.type : 'search';
        const query = isString(action.query) ? action.query : null;
        const fallbackUrl = isString(action.url) ? action.url : null;
        const sources = normaliseSources(action.sources, fallbackUrl);
        if (sources.length === 0 && (query === null || query === '')) return timeline;
        return appendSearch(timeline, actionType, query, sources);
    }

    // Anthropic native web search, announcement half. The block is emitted twice,
    // first as `started` with an empty input and then as `completed` once the
    // input JSON has streamed in, so only the completed event carries the query.
    if (event.type === 'server_tool_use' && event.status === 'completed' && event.data?.name === 'web_search') {
        const input = isRecord(event.data.input) ? event.data.input : {};
        const query = isString(input.query) ? input.query : null;
        return {
            ...timeline,
            pendingSearches: {...timeline.pendingSearches, [event.item_id]: {query}}
        };
    }

    // Anthropic native web search, result half; correlates via the tool-use id.
    if (event.type === 'web_search_tool_result') {
        const {[event.item_id]: pending = {query: null}, ...remaining} = timeline.pendingSearches;
        const sources = normaliseSources(event.data?.content, null);
        return appendSearch({...timeline, pendingSearches: remaining}, 'search', pending.query, sources);
    }

    return timeline;
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
