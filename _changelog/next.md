# v%%VERSION%%

### What's New

[//]: # (- The main new features and changes in this version.)

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)

### Bugfix

[//]: # (- List of bugs that have been fixed in this version.)
- AI requests had a fixed timeout of 60 seconds. You can now set the time with `AI_REQUEST_TIMEOUT` (default: 60 seconds, `0` = no limit). HAWKI stops requests that get no data with `AI_STALL_TIMEOUT` (default: 120 seconds, `0` = off). Both settings can also be set per provider in the `adapter` settings.

### Internals

[//]: # (- Changes that are mostly relevant to maintainers and contributors, such as refactors, dependency updates, CI changes, etc.)
- `AbstractLaravelAgent` sets the request timeout through its `timeout()` method. `DriverFactory` injects `model_providers.request_timeout` and `model_providers.stall_timeout` into the driver configuration of every provider. The `adapter` settings of a provider can override both values.
- The new `StallDetectionMiddleware` adds curl stall detection to the HTTP requests of agents. `AgentRequestScope` limits the middleware to agent requests.

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
