const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

export async function chatRequest<T>(url: string, init: RequestInit = {}): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-CSRF-TOKEN', csrfToken());
    if (init.body && !(init.body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(url, {...init, headers});
    if (!response.ok) {
        let message = `${response.status} ${response.statusText}`;
        try {
            const body = await response.json();
            message = body.message ?? Object.values(body.errors ?? {}).flat().join(' ') ?? message;
        } catch {
            // Keep the status text when the server did not return JSON.
        }
        throw new Error(message);
    }
    return response.json() as Promise<T>;
}

export function chatJson(method: string, body?: unknown): RequestInit {
    return {
        method,
        body: body === undefined ? undefined : JSON.stringify(body)
    };
}

export function getCsrfToken(): string {
    return csrfToken();
}
