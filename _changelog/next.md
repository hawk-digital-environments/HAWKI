# v%%VERSION%%

### What's New

[//]: # (- The main new features and changes in this version.)
- The same model can now be offered by several providers (e.g. `gemma-4-31b-it` on GWDG and Google). Models are identified by `<provider_id>/<model_id>`; previously the second provider's entry failed to sync.

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)

### Bugfix

[//]: # (- List of bugs that have been fixed in this version.)

### Internals

[//]: # (- Changes that are mostly relevant to maintainers and contributors, such as refactors, dependency updates, CI changes, etc.)
- `ai_models.model_id` is now unique per provider instead of globally; `system_models` references models via the new `ai_model_id` column, `system_models.model_id` was removed.
- Inside HAWKI a model is only referenced by its provider-qualified identifier `<provider_id>/<model_id>` (or its numeric id). This includes the JSON:API `model_id` of `ai-models` and `system-models`, the `system-models` `model_id` filter, chat requests on `/req/streamAI`, and the `model` stored with messages and usage records.
- A migration converts the stored model references (`ai_conv_msgs.model`, `messages.model`, `usage_records.model`) to the qualified identifier. References to models that no longer exist are left unchanged.
- The external app API (`/api/ai-req`) still accepts a plain `model_id`; when several providers offer it, the model that was created first is used (as before). A model that does not exist is rejected with `400`.
- New `ModelFinder` (`App\Services\Ai\Models\Identifiers\ModelFinder`) resolves model references, `AiModelIdentifier` builds and parses the qualified form.
- Provider keys in `model_providers.php` must not contain `/`, the config sync reports an error for them.
- The model lists (`config/model_lists/*.php`) keep their format; the config sync combines the provider key with the model ID.
- System models in `model_providers.php` (`system_models`, `default_models`, and the `*_MODEL` env variables) keep their format and also accept `<provider_id>/<model_id>`. If a plain model ID is offered by multiple providers, the config sync uses the model that was created first and logs a warning recommending the qualified ID (e.g. `openAi/gpt-5-nano`).
- `ai:tools:assign --model` and `usage:top-users --model` accept the qualified identifier; a plain model ID matches the model of every provider offering it.
- Breaking for custom code: `AiModelRepository::findOne()` / `findOneOrFail()` and `ModelFinder::find()` no longer resolve a plain `model_id`, pass `<provider_id>/<model_id>` or use `AiModelRepository::findOneByProvider()`. `SystemModel::$model_id` was removed, use `ai_model_id` / `SystemModel::model()`.

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
