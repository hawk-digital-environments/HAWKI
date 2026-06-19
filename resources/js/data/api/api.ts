import {getResourceSchema, type ResourceSchemaRegistry} from '$lib/data/resources/resourceRegistry.js';
import z from 'zod';
import {decodeJsonApiIndexResponse, decodeJsonApiResourceResponse, extendResourceCollection, extendResourceSchema, type JsonApiCollection} from '$lib/data/api/jsonApiEncoding.js';
import {buildQueryString, type FetchCollectionQuery, type FetchResourceQuery} from '$lib/data/api/buildQueryString.js';

/**
 * Options for resource fetch helpers ({@link getResourceCollectionFromApi}, {@link getResourceFromApi}).
 */
type FetchResourceApiOptions = {
    /**
     * If a resource has a registered schema, by default, we will validate the incoming data against the schema and throw an error
     * if the data does not conform. This is useful for catching API changes and ensuring type safety.
     * If there is no schema for a resource, we will skip validation and return the raw data.
     * Set "false" to skip validation even if a schema exists.
     */
    validateSchema?: boolean;
} & RequestInit;

const API_BASE_URL = '/api/hawki/v1/';

function buildApiUrl(path: string, actionOrId?: string): string {
    let url = API_BASE_URL + path;
    if (actionOrId) {
        url += `/${actionOrId}`;
    }
    return url;
}

type ApiTransport = (path: string, options: RequestInit) => Promise<any>;

let transport: ApiTransport = async (path, options) => {
    const response = await fetch(path, options);
    if (!response.ok) {
        // Attempt to parse error from JSON:API error response
        let errorMessage = `API request failed with status ${response.status}`;
        try {
            const errorResponse = await response.json();
            if (errorResponse.errors && Array.isArray(errorResponse.errors) && errorResponse.errors.length > 0) {
                errorMessage += `: ${errorResponse.errors[0].detail || errorResponse.errors[0].title || 'Unknown error'}`;
            }
        } catch (e) {
            // Ignore JSON parsing errors and use the default message
        }
        throw new Error(errorMessage);
    }
    return response.json();
};

/**
 * Replaces the default API transport function with a custom implementation.
 * @internal Used for testing and advanced use cases where you need to customize the API transport layer
 * @param customTransport
 */
export function setApiTransport(customTransport: ApiTransport) {
    transport = customTransport;
}

/**
 * Low-level fetch wrapper used by all higher-level API helpers.
 *
 * Sets the required JSON:API `Accept` header, checks for HTTP errors, and
 * attempts to extract a human-readable message from the JSON:API `errors`
 * array before throwing — so callers get "400: Validation failed" rather than
 * a generic status code.
 */
export async function fetchApi<T>(path: string, options: FetchResourceApiOptions): Promise<T> {
    const fetchOptions: RequestInit = {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/vnd.api+json,application/json',
            ...(options.headers || {})
        }
    };

    return transport(path, fetchOptions);
}

export type FetchResourceCollectionOptions = FetchResourceApiOptions & {
    /**
     * Optional query parameters for collection endpoints, such as pagination or filtering options. These will be
     * converted to a query string and appended to the URL. See {@link buildQueryString} for supported parameters.
     */
    query?: FetchCollectionQuery;
};

/**
 * Fetches the full list of a resource type from the API.
 *
 * Pass a key from {@link ResourceSchemaRegistry} (e.g. `'connections'`) to get
 * back a typed array and automatic Zod validation. Pass a plain string if the
 * resource has no registered schema — you'll get `any[]` and no validation.
 *
 * @example
 * // Typed + validated (schema must be registered for 'connections')
 * const list = await getResourceCollectionFromApi('connections');
 *
 * @example
 * // Untyped, skip validation for a one-off request
 * const raw = await getResourceCollectionFromApi('some-resource', { validateSchema: false });
 */
export async function getResourceCollectionFromApi<R extends keyof ResourceSchemaRegistry>(
    resourceType: R,
    options?: FetchResourceCollectionOptions
): Promise<JsonApiCollection<ResourceSchemaRegistry[R]>>;
export async function getResourceCollectionFromApi(
    resourceType: string,
    options?: FetchResourceCollectionOptions
): Promise<JsonApiCollection<any[]>>;
export async function getResourceCollectionFromApi(
    resourceType: string,
    options?: FetchResourceCollectionOptions
): Promise<JsonApiCollection<any>> {
    const url = buildApiUrl(resourceType) + buildQueryString(options?.query);
    const response = await fetchApi<Record<string, any>>(url, {method: 'GET', ...options});
    const data = decodeJsonApiIndexResponse(response);

    if (options?.validateSchema !== false) {
        const schema = getResourceSchema(resourceType);

        if (schema) return extendResourceCollection(response, z.array(extendResourceSchema(schema)).parse(data));
    }

    return extendResourceCollection(response, data);
}

export type FetchResourceOptions = FetchResourceApiOptions & {
    /**
     * Optional query parameters for resource endpoints. These will be
     * converted to a query string and appended to the URL. See {@link buildQueryString} for supported parameters.
     */
    query?: FetchResourceQuery;
};

/**
 * Fetches a single resource by ID from the API.
 *
 * Works the same as {@link getResourceCollectionFromApi} but hits `/{resourceType}/{id}`
 * and returns a single object rather than an array.
 *
 * @example
 * const connection = await getResourceFromApi('connections', 42);
 */
export async function getResourceFromApi<R extends keyof ResourceSchemaRegistry>(
    resourceType: R,
    id: string | number,
    options?: FetchResourceOptions
): Promise<ResourceSchemaRegistry[R]>;
export async function getResourceFromApi(
    resourceType: string,
    id: string | number,
    options?: FetchResourceOptions
): Promise<any>;
export async function getResourceFromApi(
    resourceType: string,
    id: string | number,
    options?: FetchResourceOptions
): Promise<any> {
    const url = buildApiUrl(resourceType, id.toString());
    const response = await fetchApi(url, {method: 'GET', ...options});
    const data = decodeJsonApiResourceResponse(response);

    if (options?.validateSchema !== false) {
        const schema = getResourceSchema(resourceType);
        if (schema) return extendResourceSchema(schema).parse(data);
    }

    return data;
}

type GetFromResourceActionOptions = {
    /**
     * Supply a Zod schema here if you want the response validated and narrowed to
     * a specific type. Without it the return type is `any` and the response is
     * returned as-is.
     */
    schema?: z.ZodTypeAny;
} & RequestInit;

/**
 * GETs from a custom action endpoint that doesn't follow the standard
 * resource CRUD pattern — i.e. `/{resourceType}/{action}`.
 *
 * Use this for RPC-style operations such as triggering a sync, sending a
 * message, or any other query that isn't a plain read. The response format
 * is up to the backend; unlike the `getResource*` helpers, no JSON:API
 * decoding is applied here.
 *
 * Pass `options.schema` to validate the response shape and get a typed
 * return value. Omit it for fire-and-forget calls where the response
 * structure doesn't matter.
 *
 * @example
 * const result = await getFromResourceAction('reports', 'generate', { schema: MyReportSchema });
 */
export async function getFromResourceAction<S extends z.ZodTypeAny>(
    resourceType: keyof ResourceSchemaRegistry,
    action: string,
    options: GetFromResourceActionOptions & { schema: S }
): Promise<z.infer<S>>;
export async function getFromResourceAction(
    resourceType: keyof ResourceSchemaRegistry,
    action: string,
    options?: GetFromResourceActionOptions
): Promise<any> {
    const url = buildApiUrl(resourceType, action);
    const response = await fetchApi(url, {method: 'GET', ...options});

    if (options?.schema) {
        return options.schema.parse(response);
    }
    return response;
}

/**
 * Options for {@link postToResourceAction}.
 */
type PostToResourceActionOptions = {
    /**
     * Supply a Zod schema here if you want the response validated and narrowed to
     * a specific type. Without it the return type is `any` and the response is
     * returned as-is.
     */
    schema?: z.ZodTypeAny;
} & RequestInit;

/**
 * POSTs to a custom action endpoint that doesn't follow the standard
 * resource CRUD pattern — i.e. `/{resourceType}/{action}`.
 *
 * Use this for RPC-style operations such as triggering a sync, sending a
 * message, or any other mutation that isn't a plain create/update. The
 * response format is up to the backend; unlike the `getResource*` helpers,
 * no JSON:API decoding is applied here.
 *
 * Pass `options.schema` to validate the response shape and get a typed
 * return value. Omit it for fire-and-forget calls where the response
 * structure doesn't matter.
 *
 * @example
 * const result = await postToResourceAction('ai', 'generate', payload, { schema: MySchema });
 */
export async function postToResourceAction<S extends z.ZodTypeAny>(
    resourceType: keyof ResourceSchemaRegistry,
    action: string,
    data: any,
    // Note: The options object MUST contain the 'schema' of type S
    options: PostToResourceActionOptions & { schema: S }
): Promise<z.infer<S>>;
export async function postToResourceAction(
    resourceType: keyof ResourceSchemaRegistry,
    action: string,
    data: any,
    options?: PostToResourceActionOptions
): Promise<any>;
export async function postToResourceAction(
    resourceType: keyof ResourceSchemaRegistry,
    action: string,
    data: any,
    options?: PostToResourceActionOptions
): Promise<any> {
    const url = buildApiUrl(resourceType, action);
    const response = await fetchApi(url, {
        method: 'POST',
        body: JSON.stringify(data),
        ...options
    });

    if (options?.schema) {
        return options.schema.parse(response);
    }
    return response;
}
