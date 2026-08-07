export type ApiTransport = (path: string, options: RequestInit) => Promise<any>;

export function createDefaultTransport(): ApiTransport {
    return async (path, options) => {
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
}
