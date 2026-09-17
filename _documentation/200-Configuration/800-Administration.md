# Administration

Open `/new/admin` after signing in. The panel is a separate built-in frontend plugin. It does not require unlocking the chat keychain.

## Grant access

Accounts with employee type `admin` can use every panel section. Configure that employee type in the identity provider or through the existing deployment process. Refresh the page after changing access outside the panel.

Administrators cannot disable or demote their own account. Disabled accounts lose their API tokens and cannot sign in or continue authenticated requests.

## Local accounts

Choose **Create** in the user section to add an account that does not come from LDAP, OIDC or Shibboleth. Enter a unique username and email address, an employee type and an initial password of at least 12 characters. HAWKI stores only the password hash. The user signs in through the local form and sets up their encrypted keychain on first use.

Local profile details and the sign-in password can be updated from the same section. External profile details remain controlled by the configured identity provider. Adding a password to an external account does not convert it into a local account.

## Configuration

Tables support sorting, paging and search where applicable. Edits use version checks: if another administrator changed the record, reload before editing again.

Provider and MCP credentials are write-only. An empty replacement field keeps the existing secret. Provider discovery lists remote models that are not configured yet; selected models are added disabled for review. When creating a single model, choose the provider first: the model ID field then suggests the IDs from that provider's model list that are not configured yet. Picking a suggestion fills the remaining fields from the provider's metadata (label, type, modalities, limits, pricing, flags, documentation URL) while keeping anything already typed. A custom model ID can still be typed. System model assignments require an enabled model/provider with a matching usage rule. Required system models and their providers cannot be disabled or deleted while assigned.

Imports run on the queue and preserve records edited in the panel. Test/discovery actions call the configured services. The MCP servers and tools page shows servers above the tools table. Selecting a server filters the tools below; clearing the selection shows every tool, including built-in function tools without a server. MCP discovery reconciles the selected server's reported tools; function-tool implementations remain defined in PHP.

Announcements support German and English Markdown, preview, drafts, dates and individual user targeting. The **Translate into other languages** button fills the other language from the selected one via the `translation` system model (falls back to the default model) and replaces existing text there. Database content takes precedence over the existing files. Published policies must remain global and cannot be deleted or unpublished through this panel.

## Operations

Usage combines raw records with persisted daily totals. A monthly task summarizes raw rows older than three full months before removing them. Historical provider grouping uses the current model-to-provider association.

Health refreshes every 30 seconds while visible. The scheduler writes a heartbeat each minute. AI status checks and file imports require a running queue worker. Failed-job traces and payloads are omitted from responses.

System settings allow overrides for application name, base URL, environment, timezone, default language, external authentication method, session lifetime/expiry/encryption, the AI mention handle, accessibility URL, passkey flags, external communication/app controls, upload sizes and MIME types, file retention and tool status checks. Authentication choices are LDAP, OIDC and Shibboleth; configure the chosen provider in the deployment first. Reset also restores a custom authentication service configured by the deployment.

The settings page uses System, Authentication, Features and Performance tabs, with related settings grouped in cards. Each setting shows its value and source; an override also shows the deployment default. Edit opens the validated setting form. `SystemSettings` validates an explicit list of settings and stores only overrides in `admin_settings`. Reading or applying settings never inserts defaults. Saving a value equal to the typed deployment default deletes its override. Explicit `false`, `0`, empty lists and a cleared optional URL can override nonempty defaults. Reset removes the row and restores the deployment default.

`EnvironmentConfigProxy` captures resolved Laravel configuration before the first overlay, so existing `config()` consumers receive effective values without changes to `env()` or `.env`. Locale changes also update `locale.default_language`; timezone and environment changes update Laravel's runtime state. `CacheDeploymentConfig` keeps database values out of `config:cache`, including calls through `optimize` or Artisan in PHP. Cached installations still read live overrides on boot, and resets do not require rebuilding the config cache.

Reload the browser after saving. Subsequent HTTP requests load overrides before service providers boot. Queue jobs refresh the configuration repository, but already constructed services and immutable domain configuration objects can retain their earlier values. Restart long-running workers after changing settings. Changing session encryption can invalidate existing sessions. An environment override changes runtime configuration, but does not select a different `.env` file or rerun the deployment bootstrap. Derived deployment values such as cookie names and cache prefixes remain unchanged.

The screenshots from the legacy administration include switches that have no equivalent in this version. WebAuthn, global feature switches and database/file translation switching are not added by these settings. Model capabilities remain configured in the model/tool sections; announcements remain in their own section.

The environment page shows selected effective configuration and masked secret status. Database/auth/mail/Redis/Reverb credentials, encryption keys, converter credentials and infrastructure changes remain deployment tasks; the panel does not write `.env` or restart services.

Permanent user-data deletion remains available through the existing CLI. The panel's account-disable action preserves data. Chat contents and keychains are not accessible to administrators.
