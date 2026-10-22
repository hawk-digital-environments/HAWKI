# v%%VERSION%%

### What's New

[//]: # (- The main new features and changes in this version.)

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)

### Bugfix

- Model enrichment (pricing, context windows, feature flags) stopped updating because LiteLLM discontinued their catalog API. Lookups always fell back to the static files. The catalog is now read from LiteLLM's GitHub file.

### Internals

- LiteLLM catalog: downloaded once into a shared cache and filtered per provider, kept fresh via ETag revalidation every 5 minutes. During upstream outages cached data is served for up to 24 h, then the static files take over.
- The `static_llm_data` updater no longer deletes the old files when the catalog is unreachable.

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
