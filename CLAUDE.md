# HAWKI Project

## Docker / Local Development

All commands run inside Docker via `bin/env`. Never call `php`, `composer`, `phpunit`, `node`, or `npm` directly on the host.

For the full command reference (container lifecycle, testing, code style, artisan, composer, docs, production builds), see:
`_documentation/2-GettingStarted/2-Local Docker Installation.md` — section **"The `bin/env` command reference"**

## Code style

**Never run `php-cs-fixer` / `composer php-cs-fixer` unless explicitly asked.** It reformats hundreds of unrelated files in this repo; only the user should trigger it. For verification, use `composer test:stan` (PHPStan) instead.
