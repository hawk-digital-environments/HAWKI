# v%%VERSION%%

### What's New

[//]: # (- The main new features and changes in this version.)

- Added configuration settings for file uploads, that allow administrators to specify allowed file types and maximum file sizes, enhancing security and control over user uploads. See the [environment variables](../_documentation/3-architecture/10-dot%20Env.md#File-Upload-Settings) documentation for more details.
- Improved accessiblity of the frontend by adding ARIA attributes and improving keyboard navigation, making it easier for users with disabilities to use the application. Thanks to [Thomas Orgeldinger](https://github.com/there-it-is) for the tremendous help!
- Extended supported file formats for attachments: documents (PPTX, XLSX, HTML, Markdown, AsciiDoc, CSV, WebVTT) and additional image formats (SVG, TIFF, PSD, EPS, AI, BMP, ICO) are now handled via an image pre-processing pipeline using `rsvg-convert` and ImageMagick — auto-detected from `$PATH` when available. See the [optional dependencies](../_documentation/2-GettingStarted/1-Local%20Installation.md#optional-dependencies) documentation for installation instructions.
- Added support for the [Kreuzberg](https://github.com/kreuzberg-dev/kreuzberg) open-source document extraction API as a new file converter option. Configure via `KREUZBERG_FILE_CONVERTER_API_URL` in the `.env` file. No API key is required.
- All file access (attachments and avatars) is now securely proxied through the application via a dedicated storage proxy controller, with proper access control enforced for all file types and storage backends. Direct storage URLs are no longer exposed to clients.
- Drag-and-drop file upload now provides real-time visual feedback: files are classified as valid or invalid against the server-configured allowed MIME types before the upload begins, and the file picker's `accept` attribute is automatically populated to match.

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)

- AI Model errors will now be logged to the log file in addition to being printed to the screen, making it easier to debug issues in production.
- The `VITE_REVERB_*` environment variables have been removed, as they are no longer used in the frontend. This simplifies the configuration and reduces potential confusion for users. The information about the connection will now be negotiated automatically.
- Attachment file icons are now dynamically generated SVGs with per-extension colors, replacing the previous static PNG icons for PDF and DOCX. Any file type now shows a proper labeled icon.
- Cryptographic salts are now delivered via the frontend connection payload instead of individual network requests, reducing initial page load latency.
- The frontend translation system has been rewritten. Translations are now available via a unified `__()` helper function, consistent with Laravel's backend translation API.
- HAWKI's locale and settings panel are now also available on the login and gateway pages, enabling translated UI before a user is authenticated.

### Bugfix

[//]: # (- List of bugs that have been fixed in this version.)

- `bin/env dev` now no longer dies after 300 seconds, allowing for longer-running development sessions without interruption.
- `php artisan ai:tools:mcp:add` now uses the correct api key to fetch server information, removing the hardcoded api key for the HAWKI dev environment. Thanks to Raphael Fetzer for pointing out this issue!
- The external API endpoint to HAWKI no longer throws an error if the `stream` parameter is missing, improving the robustness of the API and allowing for more flexible usage. Thanks to [willirath](https://github.com/willirath) for pointing this out and providing a [fix](https://github.com/hawk-digital-environments/HAWKI/pull/281).
- Fixed a typo in the session expiry message: "Your accound has been suspended." → "Your account has been suspended."
- Fixed a fatal error in the `PreventBackHistory` middleware when processing streaming responses that do not support HTTP headers.
- Removed the `/req/crypto/getServerSalt` server endpoint, which inadvertently exposed server-side environment variables. Cryptographic salts are now embedded in the frontend connection payload and never returned via a dedicated HTTP request.

### Internals

[//]: # (- Changes that are mostly relevant to maintainers and contributors, such as refactors, dependency updates, CI changes, etc.)

- Refactoring of the "file converter" logic, improving the handling of file conversions and reducing potential errors. Also implemented a lot of logging in this area to make it easier to debug issues related to file conversions.
- Refactoring and code cleanup of the tool calling logic, improving readability and maintainability.
- Inherited a lot of code from the `external-chat` branch, as preparation for V3 merge and to avoid merge conflicts later on. This also allows us to use the new "connection" logic to pass backend information to the frontend.
- It is now possible to implement "custom file converters" using the "file_converter.converters" array in the configuration. This allows for more flexibility and extensibility in handling file conversions, as users can now easily add their own custom converters without modifying the core codebase. A new "class" property has been added to the converter configuration, which specifies the class that should be used for the converter. This class must implement the `FileConverterInterface` and can be autoloaded using Composer's PSR-4 autoloading.
- The `FileConverterFactory` has been removed, to retrieve the converter simply ask for the `FileConverterInterface`, which will always provide you the currently configured converter. To determine if the converter is active (e.g. configured or not) check the `isAvailable()` method on the converter instance.
- Complete overhaul of the storage layer with a new value-object-driven architecture. Key new types: `StoredFile`, `FileReference`, `StoredFileIdentifier`, `StoredFileCategory`, `FileCollection`, `FileType`, `PlainTextLanguageType`. All storage service method signatures have changed — any custom code calling `FileStorageService` or `AvatarStorageService` directly must be updated.
- Every stored file now has a `.meta.json` sidecar written alongside it, capturing original filename, MIME type, extracted content references, and creation timestamp. Files without a sidecar (pre-existing uploads) have their metadata generated retroactively on first access.
- Attachment formatting for AI providers has been extracted into dedicated per-provider classes (`GoogleAttachmentFormatter`, `GwdgAttachmentFormatter`, `OllamaAttachmentFormatter`, `OpenAiAttachmentFormatter`), replacing duplicated inline logic in each request converter.
- The `AttachmentService` and `AttachmentFactory` have been replaced by a leaner `AttachmentDb` service. Stored file cleanup on attachment deletion is now handled automatically via the new `AttachmentDeleting` model event and a dedicated event listener.
- The `MessageHandlerFactory` has been removed. Message handlers (`PrivateMessageHandler`, `GroupMessageHandler`) are now resolved via Laravel's service container.
- Six new `JsonResource` classes under `Http/Resources/Legacy/` standardize JSON serialization for existing API endpoints, replacing manual inline array construction scattered across models and services.
- A new `TranslationServiceProvider` overrides Laravel's built-in translation loader to merge HAWKI's own JSON language files on top of any Laravel fallback translations. Translation labels are now embedded in the frontend connection payload.
- Added `RecursiveMerger` utility (`app/Utils/Arrays/`) with configurable deep merge behaviour, including support for unsetting keys. Exposed as `Arr::mergeRecursive()` macro.
- `AiErrorResponse` now captures a stack trace at construction time and exposes it in `toArray()` when `app.debug` is enabled, making AI provider errors significantly easier to trace during development.
- Event listeners in `app/Services/*/Listeners` are now auto-discovered via a glob registered in `bootstrap/app.php`.
- `ext-fileinfo` is now declared as a required PHP extension in `composer.json`.
- Added phpstan for static analysis which should help catch potential bugs and improve code quality. Run `composer run stan` to execute the static analysis checks. Currently NOT in the pipeline, because there are still some issues to fix, but we will get there eventually.
- The model config files of `config/model_providers.php` and `config/model_lists` are now automatically copied to `_docker_production` when a new release branch is created.
- The `jquery` library has been removed from the frontend dependencies, as it is not used in the codebase. This reduces the overall bundle size and improves performance.
- Update of all major frontend dependencies.
- Add `prettier` and `php-cs-fixer` configurations to enforce consistent code formatting across the codebase. Run `bin/env style php` or `bin/env style js` to automatically format the code according to the defined standards.
- Added `phpunit` and `phpstan` to run tests and static analysis of the main application. Run `bin/env test unit` to execute the unit tests and `bin/env test stan` to run static analysis. Run all tests and checks with `bin/env test all`.

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)

- `LanguageController::getTranslation()` and `getAvailableLanguages()` are deprecated and will be removed in a future version. Use `LocaleService` and the frontend connection payload instead.
- OpenAI requests that still run against the "/chats/completions" endpoint now trigger a warning log, to help understand why the requests are failing.
