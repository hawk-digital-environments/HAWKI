/**
 * Low-level HTTP transport contract for the {@link RestApi}.
 *
 * An `ApiTransport` is a `fetch`-shaped function `(path, options) => Promise<any>`
 * that performs the actual network call and returns the parsed JSON body.
 * `RestApi` depends on this abstraction instead of calling `fetch` directly so
 * tests (or non-browser environments like a web worker) can swap the transport
 * without touching the request-building / schema-validation logic.
 *
 * {@link createDefaultTransport} returns the browser implementation: it calls
 * `fetch` (adding the CSRF token on mutating requests, because the API is
 * session-authenticated), throws an {@link ApiTransportError} (with the first
 * JSON:API error's `detail`/`title` appended when available) on a non-2xx
 * response, and otherwise returns the parsed JSON (or `null` for responses
 * without a body, e.g. `204 No Content`).
 */
export type ApiTransport = (path: string, options: RequestInit) => Promise<any>;

function readCsrfHeaders(method: string): Record<string, string> {
    if (method === 'GET' || method === 'HEAD') {
        return {};
    }
    const cookieToken = globalThis.document?.cookie
        .split('; ')
        .find(entry => entry.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    if (cookieToken) {
        return {'X-XSRF-TOKEN': decodeURIComponent(cookieToken)};
    }
    const metaToken = globalThis.document?.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    return metaToken ? {'X-CSRF-TOKEN': metaToken} : {};
}

export function createDefaultTransport(): ApiTransport {
    return async (path, options) => {
        const headers = new Headers(options.headers);
        for (const [name, value] of Object.entries(readCsrfHeaders((options.method ?? 'GET').toUpperCase()))) {
            if (!headers.has(name)) {
                headers.set(name, value);
            }
        }

        const response = await fetch(path, {...options, headers});
        if (!response.ok) {
            // Attempt to parse error from JSON:API error response
            let errorMessage = `API request failed with status ${response.status}`;
            const serverErrorMessages: ApiTransportServerErrorMessage[] = [];
            let body: unknown;
            try {
                const errorResponse = body = await response.json();
                if (errorResponse.errors && Array.isArray(errorResponse.errors) && errorResponse.errors.length > 0) {
                    errorMessage += `: ${errorResponse.errors[0].detail || errorResponse.errors[0].title || 'Unknown error'}`;
                    serverErrorMessages.push(...errorResponse.errors.map((err: any) => ({
                        title: err.title || 'Unknown error',
                        detail: err.detail || 'No detail provided'
                    })));
                }
            } catch (e) {
                // Ignore JSON parsing errors and use the default message
            }
            throw new ApiTransportError(response.status, serverErrorMessages, body, errorMessage);
        }

        const body = await response.text();
        return body ? JSON.parse(body) : null;
    };
}
import {ApiTransportError, type ApiTransportServerErrorMessage} from '$lib/kernel/api/errors.js';
