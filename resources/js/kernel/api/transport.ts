import {ApiTransportError, type ApiTransportServerErrorMessage} from '$lib/kernel/api/errors.js';

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
 * `fetch` and, on a non-2xx response, throws an {@link ApiTransportError}
 * (carrying `status`, the raw `body`, and any parsed JSON:API `errors`) with a
 * message built from the first error's `detail`/`title` when the body is a
 * JSON:API error response, otherwise a generic "API request failed with status
 * N". On success it returns the parsed JSON body.
 */
export type ApiTransport = (path: string, options: RequestInit) => Promise<any>;

export function createDefaultTransport(): ApiTransport {
    return async (path, options) => {
        const response = await fetch(path, options);
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

            throw new ApiTransportError(
                response.status,
                serverErrorMessages,
                body,
                errorMessage
            );
        }
        return response.json();
    };
}
