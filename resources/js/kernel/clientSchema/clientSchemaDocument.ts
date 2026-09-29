import z from 'zod';

const ScalarValueSchema = z.union([z.string(), z.number(), z.boolean()]);
/** Constraint values from backend validation rules; `values` entries arrive as lists. */
const ConstraintEntrySchema = z.union([ScalarValueSchema, z.array(ScalarValueSchema)]);

/** Where an attribute may be written: a method/path pair, e.g. PATCH on the resource endpoint. */
export const WritePathSchema = z.object({
    method: z.string(),
    path: z.string()
});
export type WritePath = z.infer<typeof WritePathSchema>;

export const AttributeSchema = z.looseObject({
    /** Simplified type: `string` | `number` | `boolean` | `datetime` | `enum` | `array` | `object`. */
    type: z.string(),
    readOnly: z.boolean().optional(),
    required: z.boolean().optional(),
    constraints: z.record(z.string(), ConstraintEntrySchema).optional(),
    writable_on: z.array(WritePathSchema).optional(),
    default: ScalarValueSchema.optional()
});
export type ClientSchemaAttribute = z.infer<typeof AttributeSchema>;

export const RelationshipSchema = z.looseObject({
    cardinality: z.enum(['toOne', 'toMany']),
    /** Resource type of the related records, when the field declares an inverse. */
    type: z.string().nullable(),
    readOnly: z.boolean().optional(),
    writable_on: z.array(WritePathSchema).optional(),
    endpoints: z.object({ fetch: z.object({ method: z.string(), url: z.string() }) }).optional()
});
export type ClientSchemaRelationship = z.infer<typeof RelationshipSchema>;

export const ActionSchema = z.looseObject({
    method: z.string(),
    url: z.string(),
    allowed: z.boolean(),
    /** Input contract for JSON:API-document actions; body-less actions have none. */
    input: z.record(z.string(), z.unknown()).optional()
});
export type ClientSchemaAction = z.infer<typeof ActionSchema>;

const EndpointSchema = z.object({
    method: z.string(),
    url: z.string(),
    allowed: z.boolean()
});
export type ClientSchemaEndpoint = z.infer<typeof EndpointSchema>;

export const ResourceEndpointsSchema = z.object({
    list: EndpointSchema,
    create: EndpointSchema,
    read: EndpointSchema,
    update: EndpointSchema,
    delete: EndpointSchema
});
export type ClientSchemaResourceEndpoints = z.infer<typeof ResourceEndpointsSchema>;

/** One property of a resource's JSON-Schema block, using JSON-Schema keywords. */
export const JsonSchemaPropertySchema = z.looseObject({
    type: z.string(),
    format: z.string().optional(),
    enum: z.array(z.union([z.string(), z.number()])).optional(),
    minimum: z.number().optional(),
    maximum: z.number().optional(),
    maxLength: z.number().optional(),
    default: ScalarValueSchema.optional()
});
export type JsonSchemaProperty = z.infer<typeof JsonSchemaPropertySchema>;

/** The per-resource attribute contract with JSON-Schema keywords, for form rendering. */
export const ResourceJsonSchemaSchema = z.looseObject({
    type: z.literal('object'),
    properties: z.record(z.string(), JsonSchemaPropertySchema),
    required: z.array(z.string()).optional()
});
export type ResourceJsonSchema = z.infer<typeof ResourceJsonSchemaSchema>;

export const ClientSchemaResourceSchema = z.looseObject({
    type: z.string(),
    displayName: z.string(),
    endpoints: ResourceEndpointsSchema.optional(),
    attributes: z.record(z.string(), AttributeSchema).default({}),
    schema: ResourceJsonSchemaSchema.optional(),
    relationships: z.record(z.string(), RelationshipSchema).default({}),
    actions: z.record(z.string(), ActionSchema).default({}),
    filters: z.array(z.object({ name: z.string(), type: z.string() })).default([]),
    sortable: z.array(z.string()).default([]),
    includable: z.array(z.string()).default([])
});
export type ClientSchemaResource = z.infer<typeof ClientSchemaResourceSchema>;

export const ClientSchemaDocumentSchema = z.object({
    version: z.string(),
    generatedAt: z.string(),
    resources: z.record(z.string(), ClientSchemaResourceSchema)
});
export type ClientSchemaDocument = z.infer<typeof ClientSchemaDocumentSchema>;
