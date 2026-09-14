import z from 'zod';

export const AdminRowSchema = z.object({ id: z.string() }).catchall(z.unknown());
export const AdminFieldSchema = z.object({
    key: z.string(),
    type: z.string(),
    required: z.boolean().optional(),
    immutable: z.boolean().optional(),
    default: z.unknown().optional(),
    options: z.array(z.object({ value: z.union([z.string(), z.number()]), label: z.string() })).default([])
});
export const AdminContentSchema = z.object({
    rows: z.array(AdminRowSchema),
    columns: z.array(z.string()),
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
export type AdminRow = z.infer<typeof AdminRowSchema>;
export type AdminField = z.infer<typeof AdminFieldSchema>;
export type AdminContent = z.infer<typeof AdminContentSchema>;
