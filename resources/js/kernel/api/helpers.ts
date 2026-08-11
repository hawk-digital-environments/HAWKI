import z from 'zod';
import {type JsonApiCollection} from '$lib/kernel/api/jsonApiEncoding.js';
import type {FetchOptions, GetFromResourceActionOptions, GetResourceCollectionOptions, GetResourceOptions, PostToResourceActionOptions} from '$lib/kernel/api/RestApi.js';
import type {HawkiResourceSchemas} from '$lib/kernel/extendableTypes.js';
import {getHawkiApp} from '$lib/legacy/legacy.js';

/**
 * Low-level fetch wrapper used by all higher-level API helpers.
 *
 * Sets the required JSON:API `Accept` header, checks for HTTP errors, and
 * attempts to extract a human-readable message from the JSON:API `errors`
 * array before throwing — so callers get "400: Validation failed" rather than
 * a generic status code.
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function fetchApi<S extends z.ZodTypeAny>(
    path: string,
    options: FetchOptions & { schema: S }
): Promise<z.infer<S>>;
/**
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function fetchApi(
    path: string,
    options?: FetchOptions
): Promise<any>;
/**
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function fetchApi(
    path: string,
    options?: FetchOptions
): Promise<any> {
    return getHawkiApp().restApi.fetch(path, options);
}

/**
 * Fetches the full list of a resource type from the API.
 *
 * Pass a key from {@link HawkiResourceSchemas} (e.g. `'connections'`) to get
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
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function getResourceCollectionFromApi<R extends keyof HawkiResourceSchemas>(
    resourceType: R,
    options?: GetResourceCollectionOptions
): Promise<JsonApiCollection<HawkiResourceSchemas[R]>>;
/**
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function getResourceCollectionFromApi(
    resourceType: string,
    options?: GetResourceCollectionOptions
): Promise<JsonApiCollection<any[]>>;
/**
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function getResourceCollectionFromApi(
    resourceType: string,
    options?: GetResourceCollectionOptions
): Promise<JsonApiCollection<any>> {
    return getHawkiApp().restApi.getResourceCollection(resourceType, options);
}

/**
 * Fetches a single resource by ID from the API.
 *
 * Works the same as {@link getResourceCollectionFromApi} but hits `/{resourceType}/{id}`
 * and returns a single object rather than an array.
 *
 * @example
 * const connection = await getResourceFromApi('connections', 42);
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function getResourceFromApi<R extends keyof HawkiResourceSchemas>(
    resourceType: R,
    id: string | number,
    options?: GetResourceOptions
): Promise<HawkiResourceSchemas[R]>;
/**
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function getResourceFromApi(
    resourceType: string,
    id: string | number,
    options?: GetResourceOptions
): Promise<any>;
/**
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function getResourceFromApi(
    resourceType: string,
    id: string | number,
    options?: GetResourceOptions
): Promise<any> {
    return getHawkiApp().restApi.getResource(resourceType, id, options);
}

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
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function getFromResourceAction<S extends z.ZodTypeAny>(
    resourceType: keyof HawkiResourceSchemas,
    action: string,
    options: GetFromResourceActionOptions & { schema: S }
): Promise<z.infer<S>>;
/**
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function getFromResourceAction(
    resourceType: keyof HawkiResourceSchemas,
    action: string,
    options?: GetFromResourceActionOptions
): Promise<any> {
    return getHawkiApp().restApi.getFromResourceAction(resourceType, action, options as any);
}

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
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function postToResourceAction<S extends z.ZodTypeAny>(
    resourceType: keyof HawkiResourceSchemas,
    action: string,
    data: any,
    // Note: The options object MUST contain the 'schema' of type S
    options: PostToResourceActionOptions & { schema: S }
): Promise<z.infer<S>>;
/**
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function postToResourceAction(
    resourceType: keyof HawkiResourceSchemas,
    action: string,
    data: any,
    options?: PostToResourceActionOptions
): Promise<any>;
/**
 * @deprecated I suggest going through the `getRestApiContext()` function and using the `restApi` object directly, rather than calling this function.
 */
export async function postToResourceAction(
    resourceType: keyof HawkiResourceSchemas,
    action: string,
    data: any,
    options?: PostToResourceActionOptions
): Promise<any> {
    return getHawkiApp().restApi.postToResourceAction(resourceType, action, data, options);
}
