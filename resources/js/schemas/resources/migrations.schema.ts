import z from 'zod';

const MigrationsSchema = z.object({
    id: z.string(),
    data: z.record(z.string(), z.unknown()).nullable().optional()
}).strict();

export default MigrationsSchema;

export type Migration = z.infer<typeof MigrationsSchema>;

declare module '$lib/data/resources/resourceRegistry.js' {
    interface ResourceSchemaRegistry {
        'migrations': Migration;
    }
}
