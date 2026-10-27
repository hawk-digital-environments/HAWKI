# v%%VERSION%%

### What's New

[//]: # (- The main new features and changes in this version.)

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)

### Bugfix

[//]: # (- List of bugs that have been fixed in this version.)
- AI requests had a fixed timeout of 60 seconds, so streamed answers were aborted when a model (for example a reasoning model) sent no data for 60 seconds. Streamed requests now use `AI_STALL_TIMEOUT` (default: 120 seconds without data, `0` = off) like in version 2.4, and are never cut off while they receive data. Non-streamed requests use `AI_REQUEST_TIMEOUT` (default: 60 seconds in total, `0` = no limit). Both settings can also be set per provider in the `adapter` settings.

### Internals

[//]: # (- Changes that are mostly relevant to maintainers and contributors, such as refactors, dependency updates, CI changes, etc.)
- `DriverFactory` injects `model_providers.request_timeout` and `model_providers.stall_timeout` into the driver configuration of every provider. The `adapter` settings of a provider can override both values.
- `AbstractLaravelAgent` passes the timeout explicitly to the Laravel AI package: `send()` uses the request timeout, `sendStreaming()` the stall timeout. For streamed requests, Guzzle uses its PHP stream handler, where `timeout` is a per-read timeout and curl options are ignored. The new `AgentTimeouts` value object reads and validates both values.

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
