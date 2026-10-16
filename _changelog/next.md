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
- `ai_models.model_id` is now unique per provider instead of globally; `system_models` references models via the new `ai_model_id` column.
- The JSON:API `model_id` of `ai-models` and `system-models` is now provider-qualified (`<provider_id>/<model_id>`). Chat requests (`/req/streamAI`, `/api/ai-req`) accept this form; a plain `model_id` is still accepted as long as only one provider offers it.
- New `ModelFinder` (`App\Services\Ai\Models\Identifiers\ModelFinder`) resolves model references, `AiModelIdentifier` builds and parses the qualified form.
- Provider keys in `model_providers.php` must not contain `/`, the config sync reports an error for them.
- System models in `model_providers.php` (`system_models`, `default_models`, and the `*_MODEL` env variables) keep their format and also accept `<provider_id>/<model_id>`. If a plain model ID is offered by multiple providers, the config sync uses the model that was created first and logs a warning recommending the qualified ID (e.g. `openAi/gpt-5-nano`).

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
- `AiModelRepository::findOne()` / `findOneOrFail()` with a string `model_id`, use `ModelFinder` or `AiModelRepository::findOneByProvider()` instead. Will only accept numeric ids in the next major release.
- `system_models.model_id` (and `SystemModel::$model_id`), use `ai_model_id` / `SystemModel::model()` instead.
