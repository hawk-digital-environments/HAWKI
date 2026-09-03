import type {AssistantAppearance} from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance.js';
import {DEFAULT_ASSISTANT_COLORS} from '$plugins/core/modules/chat/components/composer/utils/assistantAppearance.js';
import type {Assistant} from '$plugins/assistants/types/assistant';
import {resolveAssistantAvatar} from '$plugins/assistants/utils/resolveAssistantAvatar';

/**
 * Builds the composer-row presentation for one real assistant from its avatar:
 *
 * - the icon is the avatar's own symbol (`avatar.name`, e.g. `"📚"`) — the glyph
 *   its creator picked in the builder. Assistants without a persisted avatar
 *   resolve to the fallback symbol, the first letter of the assistant's name
 *   (see {@link resolveAssistantAvatar});
 * - the color pair comes from the avatar's background gradient (`iconCss`): `to`
 *   is the gradient's second stop as authored, `from` is the first stop with its
 *   lightness pulled down slightly — the offset is what gives the beam and the
 *   row tint their from→to movement, since the authored stops often sit close
 *   together.
 *
 * A gradient that yields no parseable `hsl()` stop at all falls back to the
 * brand colors.
 */
export function assistantRowAppearance(assistant: Assistant): AssistantAppearance {
    const avatar = resolveAssistantAvatar(assistant.avatar, assistant.name);
    const stops = [...avatar.iconCss.matchAll(/hsl\(\s*(\d+)\s+(\d+)%\s+(\d+)%\s*\)/g)]
        .map((match): {h: number; s: number; l: number} => ({h: +match[1], s: +match[2], l: +match[3]}));

    if (stops.length === 0) {
        return {icon: avatar.name, colors: DEFAULT_ASSISTANT_COLORS};
    }

    const from = stops[0];
    const to = stops[1] ?? stops[0];
    return {
        icon: avatar.name,
        colors: {
            from: `hsl(${from.h} ${from.s}% ${Math.max(from.l - 8, 0)}%)`,
            to: `hsl(${to.h} ${to.s}% ${to.l}%)`
        }
    };
}
