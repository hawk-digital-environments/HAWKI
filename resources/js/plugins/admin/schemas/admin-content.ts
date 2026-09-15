import z from 'zod';
import type { JsonApiCollection } from '$lib/kernel/api/jsonApiEncoding.js';

export const AdminRowSchema = z.object({ id: z.string() }).catchall(z.unknown());
export const AdminFieldSchema = z.object({
    key: z.string(),
    type: z.string(),
    required: z.boolean().optional(),
    immutable: z.boolean().optional(),
    default: z.unknown().optional(),
    options: z.array(z.object({ value: z.union([z.string(), z.number()]), label: z.string() })).default([])
});
export const PermissionCatalogEntrySchema = z.object({
    name: z.string(),
    group: z.enum(['administration', 'tools']),
    title_label: z.string(),
    description_label: z.string(),
    grantable: z.boolean()
});
export const AccessRuleNameSchema = z.enum(['unavailable', 'web_search', 'image_generation', 'internal_search']);
export const AccessRuleSchema = z.object({
    name: AccessRuleNameSchema,
    title_label: z.string(),
    description_label: z.string(),
    permissions: z.array(z.string()),
    grantable: z.boolean()
});
export const RoleCatalogEntrySchema = z.object({
    id: z.number(),
    name: z.string(),
    slug: z.string(),
    is_system: z.boolean()
});
export type PermissionCatalogEntry = z.infer<typeof PermissionCatalogEntrySchema>;
export type AccessRule = z.infer<typeof AccessRuleSchema>;
export type RoleCatalogEntry = z.infer<typeof RoleCatalogEntrySchema>;

export const AdminContentSchema = z.object({
    rows: z.array(AdminRowSchema),
    permission_catalog: z.array(PermissionCatalogEntrySchema).default([]),
    access_rules: z.array(AccessRuleSchema).default([]),
    role_catalog: z.array(RoleCatalogEntrySchema).default([]),
    fields: z.array(AdminFieldSchema).default([]),
    total: z.number().optional(),
    page: z.number().optional(),
    size: z.number().optional(),
    create: z.boolean().default(false),
    delete: z.boolean().default(false),
    totals: z.record(z.string(), z.union([z.number(), z.string()])).optional(),
    versions: z.record(z.string(), z.union([z.string(), z.boolean()])).optional(),
    queues: z.record(z.string(), z.number().nullable()).optional(),
    failed_jobs: z
        .array(z.object({ uuid: z.string(), connection: z.string(), queue: z.string(), failed_at: z.string() }))
        .optional(),
    status: z.string().optional()
});
/** Any admin row; the section resource schemas in `./resources/` narrow it per section. */
export type AdminRow = z.infer<typeof AdminRowSchema>;
export type AdminField = z.infer<typeof AdminFieldSchema>;
export type AdminContent<Row extends AdminRow = AdminRow> = Omit<z.infer<typeof AdminContentSchema>, 'rows'> & {
    rows: Row[];
};

/** Converts a decoded section collection into the table and editor content. */
/**
 * Converts a decoded section collection into the table and editor content.
 * The JSON:API resource type is dropped from every row, and the editor field
 * the backend calls `type` is keyed `kind` like the row attribute it edits.
 */
export function adminContent<Row extends AdminRow>(collection: JsonApiCollection<Row>): AdminContent<Row> {
    const page = collection._meta?.page;
    const fields = Array.isArray(collection._meta?.fields) ? collection._meta.fields : undefined;
    return AdminContentSchema.parse({
        ...collection._meta,
        ...(fields ?
            { fields: fields.map((field) => (field?.key === 'type' ? { ...field, key: 'kind' } : field)) }
        :   {}),
        rows: collection.map(({ type, ...row }) => ({ ...row, _version: row._meta?.version })),
        total: page?.total,
        page: page?.currentPage,
        size: page?.perPage
    }) as AdminContent<Row>;
}
