# Dependency patches

`npm install` and `npm ci` apply these patches through `patch-package` in
`postinstall`. A patch failure stops installation.

## @tanstack/svelte-store 0.12.1

`useSelector` must retain the identity of values owned by TanStack Store.
Using `$state` wraps objects and arrays in proxies, so comparisons against
unchanged store values fail and emit `state_proxy_equality_mismatch`.
`$state.raw` preserves identity while keeping replacement values reactive.

Upstream: https://github.com/TanStack/store/issues/322

Remove the patch when an upstream release includes the fix. Run
`npm run test:admin` to verify form and field updates with Svelte dev warnings.
