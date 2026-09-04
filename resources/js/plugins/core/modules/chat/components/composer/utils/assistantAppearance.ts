import type {BeamColors} from '$lib/components/ui/border-beam/types.js';

/**
 * How one taggable assistant is presented: the icon that stands in for it, and the pair
 * of colors that identifies it everywhere it appears.
 */
export interface AssistantAppearance {
    /** The glyph shown wherever the assistant is listed or tagged — an emoji from
     *  the assistant's name, or that name's first letter. */
    icon: string;
    /**
     * The two stops the assistant's `BorderBeam` is painted from, and the pair its text and
     * backgrounds are tinted with. `from` is the deeper end, `to` the brighter one — the
     * beam sweeps between them, so the contrast within a pair is what gives the glow its
     * movement.
     */
    colors: BeamColors;
}

/**
 * The brand color pair — HAWKI's own stops, and the fallback for any row that
 * carries no colors of its own.
 */
export const DEFAULT_ASSISTANT_COLORS: BeamColors = {
    // 273° — the brand hue, at the blue-500 lightness rather than the darker brand indigo.
    from: 'oklch(0.490 0.200 273)',
    to: 'oklch(0.620 0.195 273)'
};

/**
 * Fallback appearance for any taggable assistant that carries no appearance of its own:
 * the row name's first glyph on the brand colors. Every hook-provided assistant is
 * expected to bring its own appearance (the assistants plugin derives one from the
 * assistant's name and avatar gradient — see `assistantRowAppearance` in the
 * assistants plugin).
 */
export function defaultAssistantAppearance(label: string): AssistantAppearance {
    // Code-point aware so a name starting with an emoji is not cut in half.
    return {icon: Array.from(label)[0] ?? '?', colors: DEFAULT_ASSISTANT_COLORS};
}
