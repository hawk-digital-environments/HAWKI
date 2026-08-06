# v%%VERSION%%

### What's New

- Updated the GWDG-hosted open-weight model catalog to match the current upstream lineup. **Added:** DeepSeek V4 Flash and Mistral Medium 3.5 128B. **Removed** (no longer served by GWDG): DeepSeek R1 Distill Llama 70B, InternVL 3.5 30B A3B, MedGemma 27B Instruct, Mistral Large 3 675B Instruct 2512, Qwen 3 Coder 30B A3B Instruct, and Teuken 7B Instruct Research. Administrators who pinned any of the removed models via `DEFAULT_MODEL`, `DEFAULT_FILEUPLOAD_MODEL`, or `MODELS_GWDG_*` env vars should update their configuration to use a current model ID.
- Use the latest major release of the hawki file converter. The exact version is still available in the hawki file converters root response.

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)

### Bugfix

[//]: # (- List of bugs that have been fixed in this version.)

### Internals

- Upgraded `logiscape/mcp-sdk-php` to v2.0 (major version bump). `HawkiMcpClient::callTool()` return type extended to `CallToolResult|CreateTaskResult` to match the new SDK API.
- `pdfjs-dist` upgraded to v6 (major version), `docx-preview` to ^0.4, and `katex` to ^0.18 on the frontend.
- Added `mockery/mockery ^1.6` as a dev dependency for richer mock-based testing.
- `UserFactory` refactored to align with the current `User` model schema: removed `email_verified_at`, `password`, and `remember_token`; added `username`, `employeetype`, `publicKey`, `avatar_id`, `bio`, and `isRemoved`.
- `ConfigSyncMigrationTrait` now suppresses console output when running under PHPUnit to keep test output clean.
- Removed a `@phpstan-ignore-next-line` suppression from `AppServiceProvider`, improving PHPStan compliance.
- General PHP dependency updates: `laravel/framework` ^13.23, `laravel-json-api/laravel` ^5.3, `laravel/reverb` ^1.11, `phpunit/phpunit` ^11.5, `phpstan/phpstan` ^2.2, `larastan/larastan` ^3.10, and various other packages brought to their latest compatible versions.
- General frontend dependency updates: `vite` ^8.2, `shadcn-svelte` ^1.4, `laravel-echo` ^2.4, `markstream-svelte` ^0.0.3, and related tooling.

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
