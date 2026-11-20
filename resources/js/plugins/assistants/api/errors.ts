import { ApiTransportError } from '$lib/kernel/api/errors.js';

/**
 * A single error entry as defined by the JSON:API spec.
 *
 * Laravel's json-api implementations populate at least `status`, `title`, and
 * `detail`. `source.pointer` is the JSON pointer to the offending attribute on
 * validation errors and is useful for surfacing inline form errors.
 *
 * @see https://jsonapi.org/format/#error-objects
 */
export interface ApiErrorDetail {
	id?: string;
	status?: string;
	code?: string;
	title?: string;
	detail?: string;
	source?: { pointer?: string; parameter?: string };
	meta?: Record<string, unknown>;
}

/**
 * A validation error normalized for form consumption: the offending field (the
 * last segment of the JSON pointer) plus a human-readable message. Produced by
 * {@link ApiError.fieldErrors} and consumed by the builder store to drive
 * inline, per-field error indicators.
 */
export interface FieldError {
	/** Raw JSON pointer, e.g. `/mock/attributes/handle`. */
	pointer?: string;
	/** Last segment of the pointer, e.g. `handle`. `undefined` for whole-document errors. */
	field?: string;
	/** Raw `detail` key from the server, e.g. `validation.unique`. */
	detail?: string;
	/** Human-readable message derived from `detail`/`status`. */
	message: string;
	/** HTTP status as a string, e.g. `422`. */
	status?: string;
}

/**
 * Friendly messages for the Laravel/json-api validation keys that arrive in
 * `detail`. Unmapped keys fall back to the raw `detail` string, so the system
 * degrades gracefully as the backend adds rules.
 */
const VALIDATION_MESSAGES: Record<string, string> = {
	'validation.required': 'This field is required.',
	'validation.unique': 'This value is already taken.',
	'validation.max': 'This value is too long.',
	'validation.min': 'This value is too short.',
	'validation.email': 'Please enter a valid email address.',
	'validation.url': 'Please enter a valid URL.',
	'validation.alpha_dash': 'Only letters, numbers, dashes and underscores are allowed.',
};

function humanizeDetail(detail?: string, status?: string): string {
	if (detail && VALIDATION_MESSAGES[detail]) return VALIDATION_MESSAGES[detail];
	if (detail) return detail;
	return status ? `Request failed (${status}).` : 'Invalid value.';
}

/**
 * A single error shape used by every call that flows through the API client.
 *
 * Why a custom class:
 * - Axios errors, JSON:API errors and plain network failures all need to be
 *   handled by callers the same way (mostly: "show a message, maybe redirect
 *   on 401").
 * - SvelteKit `load` functions can `throw error(404, …)` only if we know the
 *   underlying HTTP status, which axios buries under `err.response.status`.
 */
export class ApiError extends Error {
	readonly status: number;
	readonly errors: ApiErrorDetail[];
	readonly cause?: unknown;

	/**
	 * @param message  Human-readable message. Falls back to the first JSON:API
	 *                 error title or the HTTP status text.
	 * @param status   HTTP status code. `0` means the request never reached the
	 *                 server (DNS / CORS / offline).
	 * @param errors   The `errors[]` array from a JSON:API response. Empty if
	 *                 the backend did not return one.
	 * @param cause    The original error (typically an AxiosError) for
	 *                 debugging.
	 */
	constructor(message: string, status: number, errors: ApiErrorDetail[] = [], cause?: unknown) {
		super(message);
		this.name = 'ApiError';
		this.status = status;
		this.errors = errors;
		this.cause = cause;
	}

	/** True when the request was rejected because the Sanctum token is missing or invalid. */
	get isUnauthorized(): boolean {
		return this.status === 401;
	}

	/** True when the user is authenticated but lacks permission for this resource. */
	get isForbidden(): boolean {
		return this.status === 403;
	}

	/** True when the requested resource (e.g. assistant by handle) does not exist. */
	get isNotFound(): boolean {
		return this.status === 404;
	}

	/** True for Laravel form-request / json-api validation failures. Inspect `errors[].source.pointer` for field paths. */
	get isValidation(): boolean {
		return this.status === 422;
	}

	/** True when the request never reached the server (DNS / CORS / offline). */
	get isConnectionError(): boolean {
		return this.status === 0;
	}

	/**
	 * A short, user-facing message suited to a toast. Unlike {@link message}
	 * (which carries request URLs and JSON pointers for debugging), this stays
	 * generic and safe to show end users.
	 *
	 * Validation errors surface the first field message; everything else maps to
	 * a friendly line keyed off the HTTP status.
	 */
	get userMessage(): string {
		if (this.isConnectionError)	return 'Could not reach the server. Please check your connection and try again.';
		if (this.isUnauthorized) return 'Your session has expired. Please sign in again.';
		if (this.isForbidden) return 'You do not have permission to perform this action.';
		if (this.isNotFound) return 'The requested item could not be found.';
		if (this.isValidation) return this.fieldErrors[0]?.message ?? 'Please check your input.';
		return `Something went wrong (${this.status || 'network error'}). Please try again.`;
	}

	/**
	 * The errors normalized for form consumption: each entry carries the
	 * offending `field` (last segment of the JSON pointer) and a human-readable
	 * `message`. Callers map `field` to their own model keys to surface inline
	 * field errors (see the assistant builder store).
	 */
	get fieldErrors(): FieldError[] {
		return this.errors.map((e) => {
			const pointer = e.source?.pointer;
			const field = pointer ? pointer.split('/').filter(Boolean).pop() : undefined;
			return {
				pointer,
				field,
				detail: e.detail,
				status: e.status,
				message: humanizeDetail(e.detail, e.status),
			};
		});
	}

	/**
	 * Normalize any thrown value into an `ApiError`. Safe to call with axios
	 * errors, network errors, or already-wrapped `ApiError` instances (in
	 * which case it is returned unchanged).
	 */
	static from(err: unknown): ApiError {
		if (err instanceof ApiError) return err;

		const fromApiTransport = ApiError.fromApiTransportError(err);
		if (fromApiTransport) return fromApiTransport;

		const fromTransport = ApiError.fromTransportError(err);
		if (fromTransport) return fromTransport;

		const anyErr = err as {
			response?: { status?: number; statusText?: string; data?: unknown };
			config?: { url?: string; method?: string };
			message?: string;
		};
		const response = anyErr?.response;
		if (response) {
			const data = response.data as { errors?: ApiErrorDetail[] } | undefined;
			const errors = Array.isArray(data?.errors) ? data!.errors : [];
			const first = errors[0];

			// `title` alone is often a cryptic translation key
			// (`jsonapi-validation::validation.allowed_include_paths.singular`).
			// Compose detail + source so the message names the offending field,
			// then tag the request URL so we know exactly what was sent.
			const parts: string[] = [];
			if (first?.title) parts.push(first.title);
			if (first?.detail && first.detail !== first.title) parts.push(first.detail);
			if (first?.source?.parameter) parts.push(`(parameter: ${first.source.parameter})`);
			else if (first?.source?.pointer) parts.push(`(pointer: ${first.source.pointer})`);
			const baseMessage =
				parts.length > 0
					? parts.join(' — ')
					: (response.statusText ?? `HTTP ${response.status ?? 'error'}`);
			const reqUrl = anyErr?.config?.url;
			const reqMethod = anyErr?.config?.method?.toUpperCase();
			const message = reqUrl ? `${baseMessage} [${reqMethod ?? 'REQ'} ${reqUrl}]` : baseMessage;

			return new ApiError(message, response.status ?? 0, errors, err);
		}
		return new ApiError(anyErr?.message ?? 'Network error', 0, [], err);
	}

	/**
	 * The kernel's `createDefaultTransport` (`$lib/kernel/api/transport.ts`)
	 * throws a structured `ApiTransportError` on any non-2xx response — it
	 * already carries `.status` and, crucially, `.body`: the *full* raw
	 * parsed JSON:API error document, `errors[].source.pointer` included.
	 * `ApiTransportError.errors` (title/detail only, used for its own log
	 * message) and {@link fromTransportError}'s regex-reconstructed fallback
	 * both discard that pointer — which is why validation errors like "the
	 * handle has already been taken" were never reaching their field's inline
	 * error and silently fell back to nothing (the toast is suppressed for
	 * validation errors, and `apiFieldToAssistantKey` had no pointer to key
	 * off). Reading `.body.errors` directly keeps the pointer intact.
	 */
	private static fromApiTransportError(err: unknown): ApiError | null {
		if (!(err instanceof ApiTransportError)) return null;

		const body = err.body as { errors?: ApiErrorDetail[] } | undefined;
		const errors: ApiErrorDetail[] = Array.isArray(body?.errors)
			? body.errors
			: err.errors.map((e): ApiErrorDetail => ({ title: e.title, detail: e.detail, status: String(err.status) }));

		const first = errors[0];
		const parts: string[] = [];
		if (first?.title) parts.push(first.title);
		if (first?.detail && first.detail !== first.title) parts.push(first.detail);
		if (first?.source?.parameter) parts.push(`(parameter: ${first.source.parameter})`);
		else if (first?.source?.pointer) parts.push(`(pointer: ${first.source.pointer})`);
		const message = parts.length > 0 ? parts.join(' — ') : err.message;

		return new ApiError(message, err.status, errors, err);
	}

	/**
	 * Fallback for a bare `Error` shaped like `"API request failed with
	 * status 403: <detail>"` with no structured body to fall back on (e.g. a
	 * transport that isn't `ApiTransportError`). Lossy — at most one error
	 * can be reconstructed, and it never has a `source.pointer` — so
	 * {@link fromApiTransportError} is tried first and normally wins.
	 */
	private static fromTransportError(err: unknown): ApiError | null {
		if (!(err instanceof Error) || 'response' in err) return null;

		const match = /^API request failed with status (\d{3})(?::\s*(.*))?$/s.exec(err.message);
		if (!match) return null;

		const status = Number(match[1]);
		const detail = match[2]?.trim();
		// The transport keeps only the first error's detail/title, so at most one
		// entry can be reconstructed — and it has no `source.pointer`, which is
		// why `fieldErrors` stays empty until the transport is fixed.
		const errors: ApiErrorDetail[] = detail ? [{status: match[1], detail}] : [];

		return new ApiError(err.message, status, errors, err);
	}
}

/**
 * Normalize a thrown value to an {@link ApiError}, log it once with the failing
 * operation and any useful request context, then return it so callers can
 * `throw logApiError(...)` in a single line.
 *
 * Centralizes the "console.error with status + errors" pattern the resource
 * clients would otherwise repeat. Clients log and rethrow here; user-facing
 * toasts are the caller's (store's) job — see {@link ApiError.userMessage}.
 */
export function logApiError(
	operation: string,
	err: unknown,
	context?: Record<string, unknown>,
): ApiError {
	const apiError = ApiError.from(err);
	console.error(`[api] ${operation} failed`, {
		status: apiError.status,
		message: apiError.message,
		errors: apiError.errors,
		...context,
	});
	return apiError;
}
