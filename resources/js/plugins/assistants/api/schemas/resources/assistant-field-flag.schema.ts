import z from 'zod';
import { WireUserSchema } from '../wireFragments';

export interface AssistantFieldFlag {
    id: string;
    /** Which `Assistant` field this flag is about, e.g. `system_prompt`, `greeting`. */
    field: string;
    /** The selected passage, when the admin selected one; `null` for a whole-field flag. */
    excerpt: string | null;
    comment: string;
    resolved: boolean;
    adminName: string;
    createdAt: string;
    updatedAt: string;
}

/**
 * The `assistant-field-flags` JSON:API resource: an admin's flag+comment on a
 * specific field of an assistant, optionally anchored to a selected excerpt
 * (see `App\Models\Assistants\AssistantFieldFlag`). Readable by the assistant's
 * creator (it's their feedback), writable only by a site admin.
 */
const AssistantFieldFlagResourceSchema = z.object({
    id: z.string(),
    field: z.string(),
    excerpt: z.string().nullable(),
    comment: z.string(),
    resolved: z.boolean(),
    created_at: z.string(),
    updated_at: z.string(),
    admin: WireUserSchema.nullable().optional()
});

const AssistantFieldFlagSchema = AssistantFieldFlagResourceSchema.transform((wire): AssistantFieldFlag => ({
    id: wire.id,
    field: wire.field,
    excerpt: wire.excerpt,
    comment: wire.comment,
    resolved: wire.resolved,
    adminName: wire.admin?.display_name ?? '',
    createdAt: wire.created_at,
    updatedAt: wire.updated_at
}));

export default AssistantFieldFlagSchema;

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiResourceSchemas {
        'assistant-field-flags': AssistantFieldFlag;
    }
}
