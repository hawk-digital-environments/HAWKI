import Jsona from 'jsona';
import {JsonaPropertyMapper} from '$lib/data/api/JsonaPropertyMapper.js';
import type {ZodType} from 'zod';
import z from 'zod';

const encoder = new Jsona({
    jsonPropertiesMapper: new JsonaPropertyMapper()
});

export interface JsonApiPagination {
    /**
     * The current page we are showing
     */
    page: number;

    /**
     * The number of all pages we have
     */
    pages: number;

    /**
     * The maximum number of items on a single page
     */
    pageSize: number;

    /**
     * The number of all items in the set
     */
    itemCount: number;

    /**
     * Whether there is a next page after the current one
     */
    hasNextPage: boolean;

    /**
     * Whether there is a previous page before the current one
     */
    hasPreviousPage: boolean;
}

export type JsonApiCollection<T> = Array<JsonApiResource<T>> & {
    /**
     * Any additional metadata returned by the API
     */
    _meta?: Record<string, any>,
    /**
     * Any additional links returned by the API, e.g. for pagination or related resources
     */
    _links?: Record<string, string>,
    /**
     * The pagination info for this collection, if the API response included it
     */
    _pagination?: JsonApiPagination,
}

/**
 * The backend follows the JSON:API spec, which wraps every list response in
 * `{ data: [ { id, attributes: {...} }, ... ] }`. This helper flattens each
 * item so callers get plain objects like `{ id, ...fields }` instead.
 */
export function decodeJsonApiIndexResponse<T>(response: any) {
    if (response.data) {
        return encoder.deserialize(response) as Array<T>;
    }
    throw new Error('Invalid API response format: missing data field');
}

export type JsonApiResource<T> = T & {
    /**
     * Any additional metadata returned by the API for this resource
     */
    _meta?: Record<string, any>,
    /**
     * Similar to _meta, but delivered on the response level.
     */
    _globalMeta?: Record<string, any>,
    /**
     * Any additional links returned by the API for this resource, e.g. for related resources
     */
    _links?: Record<string, string>,
}

/**
 * Takes the raw API response and the decoded collection of items, and merges in
 * any additional metadata or links from the response. This allows callers to
 * access pagination info and other metadata directly on the returned array.
 */
export function extendResourceCollection<T>(response: Record<string, any>, collection: Array<T>) {
    if (!response.data) {
        throw new Error('Invalid API response format: missing data field');
    }

    const links = response.links;
    const meta = response.meta;
    const pagination = meta?.page ?? {};
    return Object.assign(collection, {
        _meta: meta,
        _links: response.links,
        _pagination: meta ? {
            page: pagination.page ?? 1,
            pages: pagination.pages ?? 1,
            pageSize: pagination.pageSize ?? collection.length,
            itemCount: pagination.itemCount ?? collection.length,
            hasNextPage: !!(links?.next),
            hasPreviousPage: !!(links?.prev)
        } : undefined
    }) as JsonApiCollection<T>;
}

/**
 * Same as {@link decodeJsonApiIndexResponse} but for a single-resource response
 * `{ data: { id, attributes: {...} } }`.
 */
export function decodeJsonApiResourceResponse<T>(response: any): T {
    if (response.data) {
        return encoder.deserialize(response) as T;
    }
    throw new Error('Invalid API response format: missing data field');
}

/**
 * Helper to extend the Zod schema for a resource with the additional metadata and links that the API may return.
 * @param schema
 */
export function extendResourceSchema<T extends ZodType>(schema: T) {
    return z.intersection(
        schema,
        z.object({
            _meta: z.record(z.string(), z.any()).optional(),
            _globalMeta: z.record(z.string(), z.any()).optional(),
            _links: z.record(z.string(), z.string()).optional()
        })
    );
}
