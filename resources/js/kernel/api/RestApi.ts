import type {ApiTransport} from '$lib/kernel/api/transport.js';
import z from 'zod';
import {decodeJsonApiIndexResponse, decodeJsonApiResourceResponse, extendResourceCollection, extendResourceSchema, type JsonApiCollection} from '$lib/kernel/api/jsonApiEncoding.js';
import {buildQueryString, type FetchCollectionQuery, type FetchResourceQuery} from '$lib/kernel/api/buildQueryString.js';
import type {HawkiResourceSchemas} from '$lib/kernel/extendableTypes.js';
import type {UriBuilder} from '$lib/kernel/api/UriBuilder.js';
import type {Locale} from '$lib/app/schemas/resources/compound/locales.schema.js';
import type {Connection} from '$lib/app/schemas/resources/connections.schema.js';

export type FetchOptions = RequestInit & {
    /**
     * Optional locale to send with the request. If provided, it will be added as a query parameter to the URL.
     * If not provided, the default locale from the connection will be used.
     */
    locale?: string | Locale;

    /**
     * Allows you to preprocess the response before it is validated by the schema.
     * This is useful if the API returns a wrapper object or needs some transformation before validation.
     */
    beforeSchema?: (response: any) => any;
    /**
     * Supply a Zod schema here if you want the response validated and narrowed to
     * a specific type. Without it the return type is `any` and the response is
     * returned as-is.
     */
    schema?: z.ZodTypeAny;
    /**
     * Allows you to postprocess the response after it is validated by the schema.
     * This is useful if you want to transform the data into a different shape or extract specific fields.
     */
    afterSchema?: (response: any, data: any) => any;
}

export type CommonGetResourceOptions = RequestInit & {
    /**
     * Optional locale to send with the request. If provided, it will be added as a query parameter to the URL.
     * If not provided, the default locale from the connection will be used.
     */
    locale?: FetchOptions['locale'];
    /**
     * If a resource has a registered schema, by default, we will validate the incoming data against the schema and throw an error
     * if the data does not conform. This is useful for catching API changes and ensuring type safety.
     * If there is no schema for a resource, we will skip validation and return the raw data.
     * Set "false" to skip validation even if a schema exists.
     */
    validateSchema?: boolean;
};

export type GetResourceOptions = CommonGetResourceOptions & {
    /**
     * Optional query parameters for resource endpoints. These will be
     * converted to a query string and appended to the URL. See {@link buildQueryString} for supported parameters.
     */
    query?: FetchResourceQuery;
};

export type GetResourceCollectionOptions = CommonGetResourceOptions & {
    /**
     * Optional query parameters for collection endpoints, such as pagination or filtering options. These will be
     * converted to a query string and appended to the URL. See {@link buildQueryString} for supported parameters.
     */
    query?: FetchCollectionQuery;
};

export type GetFromResourceActionOptions = FetchOptions;

export type PostToResourceActionOptions = FetchOptions;

export class RestApi {
    constructor(
        private readonly uriBuilder: UriBuilder,
        private readonly transport: ApiTransport,
        private readonly getConnection: () => Connection,
        private readonly getResourceSchema: (resourceType: string) => z.ZodTypeAny | undefined
    ) {
    }

    /**
     * Low-level fetch wrapper used by all higher-level API helpers.
     *
     * Sets the required JSON:API `Accept`/`Content-Type`/locale headers, then
     * delegates the actual network call to the injected {@link ApiTransport} —
     * it, not this method, checks the HTTP status and throws an `ApiTransportError`
     * with a human-readable message extracted from the JSON:API `errors` array.
     * Once the transport resolves, the response runs through `beforeSchema` →
     * `schema.parse` → `afterSchema` in that order.
     */
    public async fetch<S extends z.ZodTypeAny>(
        path: string,
        options: FetchOptions & { schema: S }
    ): Promise<z.infer<S>>;
    public async fetch(
        path: string,
        options?: FetchOptions
    ): Promise<any>;
    public async fetch(
        path: string,
        options?: FetchOptions
    ): Promise<any> {
        const fetchOptions: RequestInit = {
            ...(options || {}),
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/vnd.api+json,application/json',
                'X-App-Locale': this.getLocaleString(options || {}),
                ...(options?.headers || {})
            }
        };

        const response = await this.transport(path, fetchOptions);

        let data = options?.beforeSchema ? options.beforeSchema(response) : response;
        data = options?.schema ? options.schema.parse(data) : data;
        data = options?.afterSchema ? options.afterSchema(response, data) : data;

        return data;
    }

    /**
     * Fetches a single resource by ID from the API.
     *
     * Works the same as {@link getResourceCollection} but hits `/{resourceType}/{id}`
     * and returns a single object rather than an array.
     *
     * @example
     * const connection = await restApi.getResource('connections', 42);
     */
    async getResource<R extends keyof HawkiResourceSchemas>(
        resourceType: R,
        id: string | number,
        options?: GetResourceOptions
    ): Promise<HawkiResourceSchemas[R]>;
    async getResource(
        resourceType: string,
        id: string | number,
        options?: GetResourceOptions
    ): Promise<any>;
    async getResource(
        resourceType: string,
        id: string | number,
        options?: GetResourceOptions
    ): Promise<any> {
        const url = this.uriBuilder.jsonApiUri(resourceType, id.toString());
        const fetchOptions: FetchOptions = {
            ...options,
            beforeSchema: decodeJsonApiResourceResponse
        };
        if (options?.validateSchema !== false) {
            const schema = this.getResourceSchema(resourceType);
            if (schema) fetchOptions.schema = extendResourceSchema(schema);
        }
        return await this.fetch(url, {method: 'GET', ...fetchOptions});
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
     * const list = await getResourceCollection('connections');
     *
     * @example
     * // Untyped, skip validation for a one-off request
     * const raw = await getResourceCollection('some-resource', { validateSchema: false });
     */
    public async getResourceCollection<R extends keyof HawkiResourceSchemas>(
        resourceType: R,
        options?: GetResourceCollectionOptions
    ): Promise<JsonApiCollection<HawkiResourceSchemas[R]>>;
    public async getResourceCollection(
        resourceType: string,
        options?: GetResourceCollectionOptions
    ): Promise<JsonApiCollection<any[]>>;
    public async getResourceCollection(
        resourceType: string,
        options?: GetResourceCollectionOptions
    ): Promise<JsonApiCollection<any>> {
        const url = this.uriBuilder.jsonApiUri(resourceType) + buildQueryString(options?.query);
        const fetchOptions: FetchOptions = {
            ...options,
            beforeSchema: decodeJsonApiIndexResponse,
            afterSchema: (response, data) => extendResourceCollection(response, data)
        };
        if (options?.validateSchema !== false) {
            const schema = this.getResourceSchema(resourceType);
            if (schema) fetchOptions.schema = z.array(extendResourceSchema(schema));
        }
        return await this.fetch(url, {method: 'GET', ...fetchOptions});
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
     */
    public async getFromResourceAction<S extends z.ZodTypeAny>(
        resourceType: keyof HawkiResourceSchemas,
        action: string,
        options: GetFromResourceActionOptions & { schema: S }
    ): Promise<z.infer<S>>;
    public async getFromResourceAction(
        resourceType: keyof HawkiResourceSchemas,
        action: string,
        options?: GetFromResourceActionOptions
    ): Promise<any> {
        const url = this.uriBuilder.jsonApiUri(resourceType, action);
        return this.fetch(url, {method: 'GET', ...options});
    }

    // @todo add resource post and links

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
    public async postToResourceAction<S extends z.ZodTypeAny>(
        resourceType: keyof HawkiResourceSchemas,
        action: string,
        data: any,
        // Note: The options object MUST contain the 'schema' of type S
        options: PostToResourceActionOptions & { schema: S }
    ): Promise<z.infer<S>>;
    public async postToResourceAction(
        resourceType: keyof HawkiResourceSchemas,
        action: string,
        data: any,
        options?: PostToResourceActionOptions
    ): Promise<any>;
    public async postToResourceAction(
        resourceType: keyof HawkiResourceSchemas,
        action: string,
        data: any,
        options?: PostToResourceActionOptions
    ): Promise<any> {
        const url = this.uriBuilder.jsonApiUri(resourceType, action);
        return await this.fetch(url, {
            method: 'POST',
            body: JSON.stringify(data),
            ...options
        });
    }

    /**
     * Returns the locale string to use for API requests, based on the provided options or the default connection locale.
     *
     * Swallows the "connection not loaded yet" error and returns `''` — happens
     * during early bootstrap, before `ClientExtension` has fetched the connection.
     */
    private getLocaleString(options: FetchOptions): string {
        let locale = options?.locale;
        if (!locale) {
            try {
                locale = this.getConnection().locale;
            } catch (e) {
                // Ignore if we are too early to get the connection (e.g. during initialization)
                return '';
            }
        }
        if (typeof locale === 'string') {
            return locale;
        } else if (locale && typeof locale === 'object' && 'lang' in locale) {
            return locale.lang;
        }
        return '';
    }
}
