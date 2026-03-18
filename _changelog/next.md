# v%%VERSION%%

### What's New

[//]: # (- The main new features and changes in this version.)

- Added configuration settings for file uploads, that allow administrators to specify allowed file types and maximum file sizes, enhancing security and control over user uploads. See the [environment variables](../_documentation/3-architecture/10-dot%20Env.md#File-Upload-Settings) documentation for more details.

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)

- AI Model errors will now be logged to the log file in addition to being printed to the screen, making it easier to debug issues in production.
- The `VITE_REVERB_*` environment variables have been removed, as they are no longer used in the frontend. This simplifies the configuration and reduces potential confusion for users. The information about the connection will now be negotiated automatically.

### Bugfix

[//]: # (- List of bugs that have been fixed in this version.)

- `bin/env dev` now no longer dies after 300 seconds, allowing for longer-running development sessions without interruption.
- `php artisan ai:tools:mcp:add` now uses the correct api key to fetch server information, removing the hardcoded api key for the HAWKI dev environment. Thanks to Raphael Fetzer for pointing out this issue!
- The external API endpoint to HAWKI no longer throws an error if the `stream` parameter is missing, improving the robustness of the API and allowing for more flexible usage. Thanks to [willirath](https://github.com/willirath) for pointing this out and providing a [fix](https://github.com/hawk-digital-environments/HAWKI/pull/281).

### Internals

[//]: # (- Changes that are mostly relevant to maintainers and contributors, such as refactors, dependency updates, CI changes, etc.)

- Refactoring of the "file converter" logic, improving the handling of file conversions and reducing potential errors. Also implemented a lot of logging in this area to make it easier to debug issues related to file conversions.
- Refactoring and code cleanup of the tool calling logic, improving readability and maintainability.
- Inherited a lot of code from the `external-chat` branch, as preparation for V3 merge and to avoid merge conflicts later on. This also allows us to use the new "connection" logic to pass backend information to the frontend.
- It is now possible to implement "custom file converters" using the "file_converter.converters" array in the configuration. This allows for more flexibility and extensibility in handling file conversions, as users can now easily add their own custom converters without modifying the core codebase. A new "class" property has been added to the converter configuration, which specifies the class that should be used for the converter. This class must implement the `FileConverterInterface` and can be autoloaded using Composer's PSR-4 autoloading.
- The `FileConverterFactory` has been removed, to retrieve the converter simply ask for the `FileConverterInterface`, which will always provide you the currently configured converter. To determine if the converter is active (e.g. configured or not) check the `isAvailable()` method on the converter instance.

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
