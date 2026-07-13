import z from 'zod';
import { BACKGROUNDS } from '$lib/plugins/assistants/presets/backgrounds';
import type { Assistant, AssistantAvatar } from '$plugins/assistants/types/assistant';
import type { JsonApiResourceBody } from '../wireFragments';

/**
 * The `assistant-avatars` JSON:API resource, as it arrives from the backend
 * (see `AssistantAvatarSchema::fields()` in
 * `app/JsonApi/V1/AssistantAvatars/AssistantAvatarSchema.php`) — also the
 * shape `assistant_avatar` arrives in when inlined on an assistant response
 * (see `assistants.schema.ts`, which imports this).
 *
 * The wire's `icon_css` — not `iconCss` as {@link AssistantAvatar} names it —
 * is why this needs its own wire schema rather than validating straight
 * against the domain one: `RestApi` does no case conversion (see the doc
 * comment on `assistants.schema.ts`), so an `iconCss`-shaped schema would
 * silently fail to populate it from real wire data.
 */
export const AssistantAvatarResourceSchema = z.object({
    id: z.string().optional(),
    name: z.string(),
    icon_css: z.string()
});

/**
 * Registered by `AssistantsPlugin.resourceSchemas()` for
 * `RestApi.getResource(Collection)`. Not currently used by any read path —
 * `assistant_avatar` normally arrives inlined on an assistant response and is
 * mapped by `assistants.schema.ts` instead — but kept correct and registered
 * for whenever a standalone avatar fetch is needed.
 */
const AssistantAvatarsSchema = AssistantAvatarResourceSchema.transform((wire): AssistantAvatar => ({
    id: wire.id,
    name: wire.name,
    iconCss: wire.icon_css
}));

export default AssistantAvatarsSchema;

/**
 * The **write** half: object → JSON:API request body for
 * `POST`/`PATCH assistant-avatars`. Snaps the icon CSS back to one of the
 * known presets (see `presets/backgrounds.ts`) rather than trusting whatever
 * string the domain object carries.
 */
export function avatarToApi(
    assistant: Assistant,
    avatar: AssistantAvatar
): JsonApiResourceBody {
    return {
        type: 'assistant-avatars',
        ...(avatar.id ? { id: avatar.id } : {}),
        attributes: {
            name: avatar.name,
            icon_css: BACKGROUNDS.find((g) => g.value === avatar.iconCss)?.value,
        },
        relationships: {
            assistant: {
                data: {
                    type: 'assistants',
                    id: assistant.id,
                }
            }
        }
    };
}
