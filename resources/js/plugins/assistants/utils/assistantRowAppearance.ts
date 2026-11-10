import type { AssistantAppearance } from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance.js';
import { DEFAULT_ASSISTANT_COLORS } from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance.js';
import type { Assistant } from '$plugins/assistants/types/assistant';
import { resolveAssistantAvatar } from '$plugins/assistants/utils/resolveAssistantAvatar';

/**
 * Builds the composer-row presentation for one real assistant from its avatar:
 *
 * - the icon is the avatar's own symbol (`avatar.name`, e.g. `"📚"`) — the glyph
 *   its creator picked in the builder. Assistants without a persisted avatar
 *   resolve to the fallback symbol, the first letter of the assistant's name
 *   (see {@link resolveAssistantAvatar});
 * - the color pair comes from the avatar's two flat shades (`iconCss`:
 *   background, then foreground). The shade nearer mid lightness is used —
 *   the background on saturated grounds, the ink on pale ones — so the color
 *   reads on both light and dark surfaces; `from` is that shade with its
 *   lightness pulled down slightly, which gives the beam and the row tint
 *   their from→to movement.
 *
 * CSS that yields no parseable `hsl()` stop at all falls back to the
 * brand colors.
 */
export function assistantRowAppearance(assistant: Assistant): AssistantAppearance {
    const avatar = resolveAssistantAvatar(assistant.avatar, assistant.name);
    const stops = [...avatar.iconCss.matchAll(/hsl\(\s*(\d+)\s+(\d+)%\s+(\d+)%\s*\)/g)].map(
        (match): { h: number; s: number; l: number } => ({ h: +match[1], s: +match[2], l: +match[3] })
    );

    if (stops.length === 0) {
        return { icon: avatar.name, colors: DEFAULT_ASSISTANT_COLORS };
    }

    const color = stops.reduce((best, stop) => (Math.abs(stop.l - 50) < Math.abs(best.l - 50) ? stop : best));
    return {
        icon: avatar.name,
        colors: {
            from: `hsl(${color.h} ${color.s}% ${Math.max(color.l - 8, 0)}%)`,
            to: `hsl(${color.h} ${color.s}% ${color.l}%)`
        }
    };
}
