import type {ChatMessage} from '$plugins/core/modules/chat/types.js';

/** How close the conversation is to the model's input limit. */
export type ContextWindowUsageLevel = 'normal' | 'warning' | 'critical';

export interface ContextWindowUsage {
    /** Estimated tokens the next request will carry (prompt + output of the latest assistant reply). */
    usedTokens: number;
    /** The model's input limit. */
    maxTokens: number;
    /** usedTokens / maxTokens, clamped to 0..1. */
    ratio: number;
    /** Rounded whole percent, 0..100. At least 1 while any usage was measured so a ring never looks empty. */
    percent: number;
    level: ContextWindowUsageLevel;
}

export const CONTEXT_WINDOW_WARNING_RATIO = 0.75;
export const CONTEXT_WINDOW_CRITICAL_RATIO = 0.9;

/**
 * Estimates how full the model's context window is after the latest assistant reply.
 *
 * Every request re-sends the whole conversation, so the next one carries what the previous request already
 * carried (its prompt tokens) plus the answer the model produced for it (its output tokens). That sum is the
 * closest estimate the client can make without re-tokenizing the conversation itself, because only the
 * provider's reported usage accounts for the system prompt, attachments and tool definitions.
 *
 * The user message that is about to be typed is deliberately not counted: its size is unknown until it is sent.
 * The result is therefore a lower bound and is meant to warn early, not to predict the exact request size.
 *
 * @param messages the conversation in display order
 * @param maxInputTokens the model's input limit; `null`/`undefined` when the model does not report one
 * @returns the usage estimate, or `null` when no usable limit is known
 */
export function estimateContextWindowUsage(
    messages: readonly ChatMessage[],
    maxInputTokens: number | null | undefined
): ContextWindowUsage | null {
    if (typeof maxInputTokens !== 'number' || !Number.isFinite(maxInputTokens) || maxInputTokens <= 0) {
        return null;
    }

    const usedTokens = latestReportedUsage(messages);
    const ratio = Math.min(Math.max(usedTokens / maxInputTokens, 0), 1);

    return {
        usedTokens,
        maxTokens: maxInputTokens,
        ratio,
        percent: usedTokens > 0 ? Math.max(1, Math.round(ratio * 100)) : 0,
        level: usageLevel(ratio)
    };
}

/**
 * The prompt + output tokens of the newest assistant message that carries provider usage.
 *
 * Assistant messages without `stats` or with `promptTokens === null` are still streaming, or the provider did
 * not report usage for them; those are skipped so the estimate falls back to the last known good measurement.
 */
function latestReportedUsage(messages: readonly ChatMessage[]): number {
    for (let index = messages.length - 1; index >= 0; index--) {
        const message = messages[index];
        if (message.message_role !== 'assistant') {
            continue;
        }
        const promptTokens = message.stats?.promptTokens;
        if (typeof promptTokens !== 'number') {
            continue;
        }
        return promptTokens + (message.stats?.outputTokens ?? 0);
    }
    return 0;
}

function usageLevel(ratio: number): ContextWindowUsageLevel {
    if (ratio >= CONTEXT_WINDOW_CRITICAL_RATIO) {
        return 'critical';
    }
    return ratio >= CONTEXT_WINDOW_WARNING_RATIO ? 'warning' : 'normal';
}
