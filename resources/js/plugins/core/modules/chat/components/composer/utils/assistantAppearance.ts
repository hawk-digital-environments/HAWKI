import type { BeamColors } from '$lib/components/ui/border-beam/types.js';

/**
 * How one taggable assistant is presented: the emoji that stands in for it, and the pair of
 * colors that identifies it everywhere it appears.
 */
export interface AssistantAppearance {
    /** Emoji glyph shown wherever the assistant is listed or tagged. */
    emoji: string;
    /**
     * The two stops the assistant's `BorderBeam` is painted from, and the pair its text and
     * backgrounds are tinted with. `from` is the deeper end, `to` the brighter one — the
     * beam sweeps between them, so the contrast within a pair is what gives the glow its
     * movement.
     */
    colors: BeamColors;
}

/**
 * Presentation for the taggable assistants, keyed by `AiAssistantHandle.id`.
 *
 * Shared by the `@` button menu (`AssistantMenu`), the caret-anchored mention popup
 * (`AssistantMentionPopup`) and the composer chips (`MentionChip`), so an assistant reads
 * as the same emoji and the same colors wherever it shows up.
 *
 * Written in OKLCH, and built rather than picked. The six hues sit exactly 60° apart
 * starting from 273 — HAWKI's brand hue — so no two assistants can land near enough to be
 * confused, and none of the wheel goes unused. Within a pair both stops share a hue and run
 * 0.13 apart in lightness, which is what gives the deep → bright ramp its lift.
 *
 * The whole set sits high and bright on purpose. Each pair is placed most of the way toward
 * its own hue's most saturated lightness and then clamped into a 0.62–0.84 band, which is
 * where contemporary UI palettes live — a blue holds up around 0.62 while an amber or a
 * cyan wants 0.80, and flattening them all to one lightness is exactly what turns a gold
 * into mustard and a teal into slate. Chroma is likewise per-hue, as much as each can
 * carry, capped at 0.20 so nothing tips into neon.
 */
const ASSISTANT_APPEARANCE: Record<string, AssistantAppearance> = {
    // 273° — the brand hue, at the blue-500 lightness rather than the darker brand indigo.
    hawki: { emoji: '🤖', colors: { from: 'oklch(0.490 0.200 273)', to: 'oklch(0.620 0.195 273)' } },
    // 93° — amber.
    tutor: { emoji: '🎓', colors: { from: 'oklch(0.703 0.137 93)', to: 'oklch(0.833 0.162 93)' } },
    // 213° — cyan.
    research: { emoji: '🔬', colors: { from: 'oklch(0.672 0.112 213)', to: 'oklch(0.802 0.133 213)' } },
    // 333° — fuchsia.
    writing: { emoji: '✍️', colors: { from: 'oklch(0.568 0.200 333)', to: 'oklch(0.698 0.200 333)' } },
    // 33° — vermilion.
    exam: { emoji: '📚', colors: { from: 'oklch(0.537 0.188 33)', to: 'oklch(0.667 0.200 33)' } },
    // 153° — emerald.
    code: { emoji: '💻', colors: { from: 'oklch(0.703 0.172 153)', to: 'oklch(0.833 0.200 153)' } }
};

/** Falls back to the generic bot on the brand colors, so a server-provided assistant we
 *  have no entry for still renders as something. */
const FALLBACK_APPEARANCE: AssistantAppearance = ASSISTANT_APPEARANCE.hawki;

/** Returns the assistant's emoji and color stops. */
export function getAssistantAppearance(id: string): AssistantAppearance {
    return ASSISTANT_APPEARANCE[id] ?? FALLBACK_APPEARANCE;
}
