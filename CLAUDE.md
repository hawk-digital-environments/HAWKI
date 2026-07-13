# HAWKI Project

## Docker / Local Development

All commands run inside Docker via `bin/env`. Never call `php`, `composer`, `phpunit`, `node`, or `npm` directly on the host.

For the full command reference (container lifecycle, testing, code style, artisan, composer, docs, production builds), see:
`_documentation/2-GettingStarted/2-Local Docker Installation.md` — section **"The `bin/env` command reference"**

## New Svelte Frontend vs. Legacy Code

The new Svelte frontend (`resources/js/`) is a clean rewrite. It must stay decoupled from the legacy UI:

- **Never use the legacy API endpoints from the new frontend, and never extend them.** The new frontend talks to the JSON:API (`app/JsonApi/`, `app/Http/Controllers/Api/`). If an endpoint is missing, add a JSON:API resource — do not add routes or methods to legacy controllers.
- **Never use `oldUiBridge`, legacy global state, or any JavaScript in `public/js/` for rendering in the new frontend.** The legacy globals are all considered legacy; avoid them.
- **Don't patch legacy files (`public/js/*`) in parallel with new-frontend work.** Shared logic belongs in one place the new code owns (server-side or a kernel/TS module), not duplicated into both UIs.

## Frontend Conventions

- **Breakpoints: never invent ad-hoc `@media (max-width: …)` values.** Always use the existing `@custom-media` tokens from `resources/css/tokens/breakpoints.css` (in TS: `breakpointsQueries` from `resources/js/components/util/breakpoints/breakpoints.ts`). If a new breakpoint is truly required, add it to the token file following the existing naming scheme including all variations (e.g. `--bp-mob` **and** `--bp-bigger-than-mob`, see how `xxs` is defined) — never a raw pixel value in a component.
- **Reuse before re-implementing.** Check `resources/js/components/ui/` and `resources/js/utils/` (e.g. Loader/Spinner, `proximityHover`) and the kernel HTTP layer (`app.restApi` / `kernel/api`) before hand-rolling a duplicate fetch wrapper, spinner, or hover effect. Extract shared visuals into `components/ui/` instead of copy-pasting.
- **All UI strings go through the translator** (`__()` / `useTranslator`, `resources/language/ui_de_DE.json` + `ui_en_US.json`). No hardcoded German or English strings in components.
- **Use the router's named routes and kernel APIs** instead of hardcoding route prefixes like `/chat`.

## Working Style

- **Keep changes minimal and focused.** Prefer the smallest diff that solves the problem; don't restructure files as a side effect. Frontend tasks should not require touching `app/` (backend) unless the task is explicitly about the API.

## Code style

**Never run `php-cs-fixer` / `composer php-cs-fixer` unless explicitly asked.** It reformats hundreds of unrelated files in this repo; only the user should trigger it. For verification, use `composer test:stan` (PHPStan) instead.
