import type {RestApi} from '$lib/kernel/api/RestApi.js';
import type {
    ClientSchemaDocument,
    ClientSchemaRelationship,
    ClientSchemaResource,
    ResourceJsonSchema,
    WritePath
} from '$lib/kernel/clientSchema/clientSchemaDocument.js';

let cached: Promise<ClientSchemaDocument> | null = null;

/**
 * Fetches the client schema once per session. Concurrent callers share the
 * same request; a failed fetch drops the cache so the next call retries.
 * Call {@link invalidateClientSchema} when the session or the user's
 * permissions change.
 */
export function fetchClientSchema(restApi: RestApi): Promise<ClientSchemaDocument> {
    if (!cached) {
        cached = restApi.getClientSchema().catch((failure: unknown) => {
            cached = null;
            throw failure;
        });
    }
    return cached;
}

export function invalidateClientSchema(): void {
    cached = null;
}

export function getResource(document: ClientSchemaDocument, resourceType: string): ClientSchemaResource | undefined {
    return document.resources[resourceType];
}

export function jsonSchema(document: ClientSchemaDocument, resourceType: string): ResourceJsonSchema | undefined {
    return getResource(document, resourceType)?.schema;
}

export type ResourceEndpointKey = 'list' | 'create' | 'read' | 'update' | 'delete';

export function isEndpointAllowed(
    document: ClientSchemaDocument,
    resourceType: string,
    endpoint: ResourceEndpointKey
): boolean {
    return getResource(document, resourceType)?.endpoints?.[endpoint]?.allowed === true;
}

/** Write paths of one attribute; `undefined` when the attribute or resource is unknown. */
export function writableOn(
    document: ClientSchemaDocument,
    resourceType: string,
    attribute: string
): WritePath[] | undefined {
    const paths = getResource(document, resourceType)?.attributes[attribute]?.writable_on;
    return paths && paths.length > 0 ? paths : undefined;
}

export function isWritableAttribute(
    document: ClientSchemaDocument,
    resourceType: string,
    attribute: string,
    method: string = 'PATCH'
): boolean {
    return (
        writableOn(document, resourceType, attribute)?.some((path) => path.method === method.toUpperCase()) === true
    );
}

export function attributeDefault(
    document: ClientSchemaDocument,
    resourceType: string,
    attribute: string
): string | number | boolean | undefined {
    return getResource(document, resourceType)?.attributes[attribute]?.default;
}

/** Enum values of an attribute from the JSON-Schema block, when it defines one. */
export function enumValues(
    document: ClientSchemaDocument,
    resourceType: string,
    attribute: string
): (string | number)[] | undefined {
    const property = jsonSchema(document, resourceType)?.properties[attribute];
    const values = property?.enum;
    return values && values.length > 0 ? values : undefined;
}

export function requiredAttributes(document: ClientSchemaDocument, resourceType: string): string[] {
    return jsonSchema(document, resourceType)?.required ?? [];
}

export function relationship(
    document: ClientSchemaDocument,
    resourceType: string,
    name: string
): ClientSchemaRelationship | undefined {
    return getResource(document, resourceType)?.relationships[name];
}

export function actionUrl(
    document: ClientSchemaDocument,
    resourceType: string,
    action: string
): string | undefined {
    return getResource(document, resourceType)?.actions[action]?.url;
}
