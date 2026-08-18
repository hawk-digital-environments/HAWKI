# SSRF Protection

All outbound HTTP requests from the backend must use `Http::getSsrfSafe()` rather than `Http::get()`. The macro is registered by `App\Services\System\Http\SsrfSafeGetterMacro`.

## Why this exists

This macro was added in response to a Server-Side Request Forgery (SSRF) security advisory (CWE-918). An authenticated user could make the HAWKI server issue `GET` requests to arbitrary internal hosts and exfiltrate response metadata — the `LinkPreviewController` fetched attacker-supplied URLs server-side with `Http::get()` and no IP-range validation, no DNS-resolution check, and redirect following enabled (Guzzle default).

## What it does

The macro validates every URL — including every intermediate redirect hop — against a public-IP allowlist. Requests that resolve to internal subnets (RFC 1918, loopback, link-local, unique-local, `0.0.0.0/8`, IPv4-mapped IPv6) are blocked with a `SsrfBlockedException`. This protects against SSRF attacks where user-supplied URLs are used to reach internal services, including via redirect-to-internal and alternate IP encodings (octal, decimal, hex, `::ffff:` mapping).

## When it applies

There is no automatism — `Http::getSsrfSafe()` is not a global override. Every HTTP call where the URL (or any part of it) originates from user input and the response is returned to the user **must** be routed through this macro manually. This prevents an authenticated user from scanning the server's internal network or exfiltrating internal service metadata. If you are adding an endpoint that fetches a user-supplied URL server-side, use `Http::getSsrfSafe()` instead of `Http::get()`. Calls to hard-coded internal services (e.g. a known AI provider API) do not need it.
