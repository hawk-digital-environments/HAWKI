import {ApiTransportError, type ApiTransportServerErrorMessage} from '$lib/kernel/api/errors.js';

/**
 * Low-level HTTP transport contract for the {@link RestApi}.
 *
 * An `ApiTransport` is a `fetch`-shaped function `(path, options) => Promise<unknown>`
 * that performs the actual network call and returns the parsed JSON body — or,
 * with `responseType: 'stream'`, the raw response body stream for streaming APIs.
 * `RestApi` depends on this abstraction instead of calling `fetch` directly so
 * tests (or non-browser environments like a web worker) can swap the transport
 * without touching the request-building / schema-validation logic.
 *
 * {@link createDefaultTransport} returns the browser implementation: it calls
 * `fetch` (adding the CSRF token on mutating requests, because the API is
 * session-authenticated) and, on a non-2xx response, throws an
 * {@link ApiTransportError} (carrying `status`, the raw `body`, and any parsed
 * JSON:API `errors`) with a message built from the first error's
 * `detail`/`title` when the body is a JSON:API error response, otherwise a
 * generic "API request failed with status N". On success it returns the parsed
 * JSON body (or `null` for responses without a body, e.g. `204 No Content`).
 */
export type ApiTransportOptions = RequestInit & {
    /** Return the response body without consuming it. Used by streaming APIs. */
    responseType?: 'json' | 'stream';
};

export interface ApiTransport {
    (path: string, options: ApiTransportOptions & {responseType: 'stream'}): Promise<ReadableStream<Uint8Array>>;
    (path: string, options?: ApiTransportOptions): Promise<unknown>;
}

/**
 * Reads the CSRF value for every mutating request because Laravel may rotate
 * the session/XSRF token after authentication or session regeneration.
 */
function readCsrfHeaders(method: string): Record<string, string> {
    if (method === 'GET' || method === 'HEAD') {
        return {};
    }

    const cookieToken = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    if (cookieToken) {
        return {'X-XSRF-TOKEN': decodeURIComponent(cookieToken)};
    }

    const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    return metaToken ? {'X-CSRF-TOKEN': metaToken} : {};
}

export function createDefaultTransport(): ApiTransport {
    return (async (path: string, options: ApiTransportOptions = {}) => {
        const {responseType = 'json', ...requestOptions} = options;
        const headers = new Headers(requestOptions.headers);
        for (const [name, value] of Object.entries(
            readCsrfHeaders((requestOptions.method ?? 'GET').toUpperCase())
        )) {
            if (!headers.has(name)) {
                headers.set(name, value);
            }
        }

        const response = await fetch(path, {...requestOptions, headers});
        if (!response.ok) {
            throw await createTransportError(response);
        }

        if (responseType === 'stream') {
            if (!response.body) {
                throw new ApiTransportError(
                    response.status,
                    [],
                    null,
                    'API response did not include a readable stream.'
                );
            }
            return response.body;
        }

        // DELETE and action endpoints legitimately return 204/empty bodies.
        const body = await response.text();
        return body ? JSON.parse(body) : null;
    }) as ApiTransport;
}

async function createTransportError(response: Response): Promise<ApiTransportError> {
    let errorMessage = `API request failed with status ${response.status}`;
    const serverErrorMessages: ApiTransportServerErrorMessage[] = [];
    const rawBody = await response.text().catch(() => '');
    let body: unknown = rawBody || null;

    if (rawBody) {
        try {
            body = JSON.parse(rawBody);
        } catch {
            errorMessage += `: ${rawBody}`;
        }
    }

    if (body && typeof body === 'object' && 'errors' in body && Array.isArray(body.errors)) {
        const errors = body.errors as Array<Record<string, unknown>>;
        serverErrorMessages.push(...errors.map((error) => ({
            title: typeof error.title === 'string' ? error.title : 'Unknown error',
            detail: typeof error.detail === 'string' ? error.detail : 'No detail provided'
        })));
        const first = serverErrorMessages[0];
        if (first) {
            errorMessage += `: ${first.detail || first.title}`;
        }
    }

    return new ApiTransportError(response.status, serverErrorMessages, body, errorMessage);
}
