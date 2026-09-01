/**
 * Derived, display-oriented facts about an `AiModel` for the model card:
 * a 1–5 price tier computed from the backend's per-token pricing, a 1–5
 * intelligence tier estimated from model signals, and a compact token-count
 * formatter ("922k", "1.5M").
 *
 * The intelligence tier is a client-side HEURISTIC — the backend has no
 * capability score, so it is estimated from reasoning support, context window
 * size, and price class. Replace with a real backend field if one is added.
 */
import type {AiModel} from '$plugins/core/schemas/resources/ai-models.schema.js';

/** Narrows the loosely-typed `limits` (only strictly typed on chat models). */
export function getModelLimits(model: AiModel): {max_input_tokens?: number | null; max_output_tokens?: number | null} | null {
    if (!('limits' in model) || !model.limits || typeof model.limits !== 'object') {
        return null;
    }
    return model.limits as {max_input_tokens?: number | null; max_output_tokens?: number | null};
}

/**
 * Price tier on a 1 (cheapest, incl. free) to 5 (most expensive) scale, based
 * on the standard-tier output cost per million tokens. `null` when the model
 * has no pricing information (e.g. self-hosted models).
 */
export function getModelPriceTier(model: AiModel): number | null {
    if (!('pricing' in model) || !model.pricing || typeof model.pricing !== 'object') {
        return null;
    }
    // `pricing` is `unknown` on non-chat models, so narrow the shape by hand.
    const pricing = model.pricing as {free?: boolean; ranges?: Array<{output_cost_per_token?: number}> | null};
    if (pricing.free !== undefined) {
        return pricing.free ? 1 : null;
    }
    const costPerMillion = (pricing.ranges?.[0]?.output_cost_per_token ?? 0) * 1_000_000;
    if (costPerMillion <= 0) {
        return null;
    }
    if (costPerMillion < 1) return 1;
    if (costPerMillion < 3) return 2;
    if (costPerMillion < 8) return 3;
    if (costPerMillion < 20) return 4;
    return 5;
}

/**
 * Heuristic intelligence tier on a 1–5 scale: models earn points for
 * reasoning/thinking support, a large context window, and a high price class
 * (larger models cost more to run). Purely an orientation aid for users.
 */
export function getModelIntelligenceTier(model: AiModel): number {
    const flags = model.flags ?? [];
    const priceTier = getModelPriceTier(model) ?? 0;
    const contextWindow = getModelLimits(model)?.max_input_tokens ?? 0;

    let score = 1;
    if (flags.includes('strength-reasoning') || flags.some(flag => flag.startsWith('feature-reasoning'))) {
        score += 1;
    }
    if (contextWindow >= 128_000) {
        score += 1;
    }
    if (priceTier >= 3) {
        score += 1;
    }
    if (priceTier >= 5) {
        score += 1;
    }
    return Math.min(score, 5);
}

/**
 * Compact token-count formatting: `922000` → `"922k"`, `1500000` → `"1.5M"`
 * (decimal separator localized via `htmlLang`, e.g. `"1,5M"` for German).
 */
export function formatTokenCount(count: number, htmlLang: string): string {
    const format = new Intl.NumberFormat(htmlLang, {maximumFractionDigits: 1});
    if (count >= 1_000_000) {
        return `${format.format(count / 1_000_000)}M`;
    }
    if (count >= 1_000) {
        return `${format.format(Math.round(count / 1_000))}k`;
    }
    return format.format(count);
}
