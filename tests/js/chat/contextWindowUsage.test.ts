import assert from 'node:assert/strict';
import {describe, test} from 'node:test';
import type {ChatMessage, MessageStats} from '../../../resources/js/plugins/core/modules/chat/types.js';
import {
    CONTEXT_WINDOW_CRITICAL_RATIO,
    CONTEXT_WINDOW_WARNING_RATIO,
    estimateContextWindowUsage
} from '../../../resources/js/plugins/core/modules/chat/utils/contextWindowUsage.js';

/** A minimal message; only the fields the estimate reads are filled in. */
function message(overrides: Partial<ChatMessage> = {}): ChatMessage {
    return {message_role: 'user', message_id: '1.000', ...overrides} as ChatMessage;
}

function stats(promptTokens: number | null, outputTokens: number | null = null): MessageStats {
    return {promptTokens, outputTokens, tokensPerSecond: null, timeToFirstTokenMs: null, durationMs: 0};
}

function assistant(promptTokens: number | null, outputTokens: number | null = null): ChatMessage {
    return message({message_role: 'assistant', stats: stats(promptTokens, outputTokens)});
}

describe('estimateContextWindowUsage', () => {
    test('returns null without a usable input limit', () => {
        const messages = [assistant(100)];
        assert.equal(estimateContextWindowUsage(messages, null), null);
        assert.equal(estimateContextWindowUsage(messages, undefined), null);
        assert.equal(estimateContextWindowUsage(messages, 0), null);
        assert.equal(estimateContextWindowUsage(messages, -1000), null);
        assert.equal(estimateContextWindowUsage(messages, Number.NaN), null);
        assert.equal(estimateContextWindowUsage(messages, Number.POSITIVE_INFINITY), null);
    });

    test('reports an empty window while no assistant reply has been measured', () => {
        for (const messages of [[], [message()], [message({message_role: 'assistant'})]]) {
            assert.deepEqual(estimateContextWindowUsage(messages, 1000), {
                usedTokens: 0,
                maxTokens: 1000,
                ratio: 0,
                percent: 0,
                level: 'normal'
            });
        }
    });

    test('uses the newest assistant reply that reported usage', () => {
        const usage = estimateContextWindowUsage(
            [assistant(100, 10), message(), assistant(400, 50), message()],
            1000
        );
        assert.deepEqual(usage, {usedTokens: 450, maxTokens: 1000, ratio: 0.45, percent: 45, level: 'normal'});
    });

    test('skips a still streaming reply and falls back to the last measured one', () => {
        const messages = [
            assistant(200, 20),
            message(),
            assistant(null),
            message({message_role: 'assistant'})
        ];
        assert.equal(estimateContextWindowUsage(messages, 1000)?.usedTokens, 220);
    });

    test('counts a missing output token count as zero', () => {
        assert.equal(estimateContextWindowUsage([assistant(300, null)], 1000)?.usedTokens, 300);
        assert.equal(estimateContextWindowUsage([assistant(300, 0)], 1000)?.usedTokens, 300);
    });

    test('clamps the ratio once the reported usage exceeds the limit', () => {
        const usage = estimateContextWindowUsage([assistant(1200, 400)], 1000);
        assert.deepEqual(usage, {usedTokens: 1600, maxTokens: 1000, ratio: 1, percent: 100, level: 'critical'});
    });

    test('switches level at the warning and critical thresholds', () => {
        const levelAt = (percent: number) => estimateContextWindowUsage([assistant(percent)], 100)?.level;
        assert.equal(levelAt(74), 'normal');
        assert.equal(levelAt(CONTEXT_WINDOW_WARNING_RATIO * 100), 'warning');
        assert.equal(levelAt(89), 'warning');
        assert.equal(levelAt(CONTEXT_WINDOW_CRITICAL_RATIO * 100), 'critical');
    });

    test('rounds the percent to a whole number', () => {
        assert.equal(estimateContextWindowUsage([assistant(1)], 3)?.percent, 33);
        assert.equal(estimateContextWindowUsage([assistant(2)], 3)?.percent, 67);
    });

    test('shows at least one percent once any usage was measured', () => {
        // 334 of 922k tokens is 0.04 %, but an empty ring would look like nothing is tracked.
        assert.equal(estimateContextWindowUsage([assistant(311, 23)], 922_000)?.percent, 1);
        assert.equal(estimateContextWindowUsage([], 922_000)?.percent, 0);
    });
});
