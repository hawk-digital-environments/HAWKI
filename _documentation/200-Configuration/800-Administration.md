# Administration

Open `/new/admin` after signing in. The panel is a separate built-in frontend plugin. It does not require unlocking the chat keychain.

## Grant access

```bash
bin/env artisan rbac:grant tester admin
bin/env artisan rbac:grant USERNAME ROLE_SLUG
bin/env artisan rbac:grant USERNAME ROLE_SLUG --revoke
```

The administrator role has every registered permission. The `user` role starts without administrative permissions. Custom roles need `admin.access` plus the permissions for the sections they should use. Refresh the page after a role is assigned outside the panel.

| Permission             | Access                                                                                             |
| ---------------------- | -------------------------------------------------------------------------------------------------- |
| `admin.access`         | Open the panel                                                                                     |
| `users.view`           | List users                                                                                         |
| `users.manage`         | Create local accounts, reset their passwords, disable/re-enable accounts and inspect/revoke tokens |
| `roles.manage`         | Manage custom roles, manual assignments and employee-type mappings                                 |
| `providers.manage`     | Manage providers and replace credentials                                                           |
| `models.manage`        | Manage models, descriptions, system models and prompts                                             |
| `mcp.manage`           | Manage MCP servers, discovered tools and assignments                                               |
| `announcements.manage` | Edit, target, schedule and publish announcements                                                   |
| `usage.view`           | Aggregate usage and CSV export                                                                     |
| `usage.view-per-user`  | User-level usage, in addition to `usage.view`                                                      |
| `health.view`          | View health, queues and failed-job metadata                                                        |
| `health.manage`        | Run status checks and retry/remove failed jobs                                                     |
| `settings.manage`      | Edit/reset runtime overrides                                                                       |
| `settings.view`        | View masked environment diagnostics                                                                |
| `external-apps.manage` | Existing administrative external-app access                                                        |

Built-in roles cannot be deleted and keep their slug, but their name, description and permissions can be edited. Administrators cannot grant permissions they lack, remove their own panel access, disable themselves or remove the last active role administrator. Role changes take effect on the next API request. Disabled accounts lose their API tokens and cannot sign in or continue authenticated requests.

Employee-type mappings use exact strings. Each employee type maps to one role. Saving or deleting a mapping updates matching existing users immediately; login synchronizes derived assignments again. Manual assignments remain separate and survive changes to employee type.

## Local accounts

Choose **Create** in the user section to add an account that does not come from LDAP, OIDC or Shibboleth. Enter a unique username and email address, an employee type and an initial password of at least 12 characters. HAWKI stores only the password hash. The user signs in through the local form and sets up their encrypted keychain on first use.

Local profile details and the sign-in password can be updated from the same section. External profile details remain controlled by the configured identity provider. Adding a password to an external account does not convert it into a local account.

## Configuration

Tables support sorting, paging and search where applicable. Edits use version checks: if another administrator changed the record, reload before editing again. Setting updates and resets use the same checks, including settings that still use deployment defaults.

Provider and MCP credentials are write-only. An empty replacement field keeps the existing secret. Provider discovery lists remote models that are not configured yet; selected models are added disabled for review. When creating a single model, choose the provider first: the model ID field then suggests the IDs from that provider's model list that are not configured yet. Picking a suggestion fills the remaining fields from the provider's metadata (label, type, modalities, limits, pricing, flags, documentation URL) while keeping anything already typed. A custom model ID can still be typed. System model assignments require an enabled model/provider with a matching usage rule. Required system models and their providers cannot be disabled or deleted while assigned.

The model editor can limit a model to selected roles. Leaving allowed roles empty makes the model available to everyone. Selecting roles hides the model from other users and prevents them from starting or continuing requests with it. System models cannot be restricted, and restricted models cannot be assigned to a system slot.

Imports run on the queue and preserve records edited in the panel. Test/discovery actions call the configured services. The MCP servers and tools page lists the servers; expanding a server row shows its tools with activation, access rules, capability mapping and model assignments. Built-in function tools without a server are listed in a separate section below the servers. The expanded server is kept in the URL (`mcp_server_id`) so it can be linked. MCP discovery reconciles the selected server's reported tools while preserving activation and description edits made in the panel; function-tool implementations remain defined in PHP.

Announcements support German and English Markdown, preview, drafts, dates and role/user targeting. The **Translate into other languages** button fills the other language from the selected one via the `translation` system model (falls back to the default model) and replaces existing text there. Database content takes precedence over the existing files. Published policies must remain global and cannot be deleted or unpublished through this panel.

## Operations

Usage combines raw records with persisted daily totals. A monthly task summarizes raw rows older than three full months before removing them. User breakdowns require the separate per-user permission. Historical provider grouping uses the current model-to-provider association.

Health refreshes every 30 seconds while visible. The scheduler writes a heartbeat each minute. AI status checks and file imports require a running queue worker. Failed-job traces and payloads are omitted from responses.

System settings allow overrides for application name, base URL, environment, timezone, default language, external authentication method, session lifetime/expiry/encryption, the AI mention handle, accessibility URL, passkey flags, external communication/app controls, upload sizes and MIME types, file retention and tool status checks. Authentication choices are LDAP, OIDC and Shibboleth; configure the chosen provider in the deployment first. Reset also restores a custom authentication service configured by the deployment.

The settings page uses System, Authentication, Features and Performance tabs, with related settings grouped in cards. Each setting shows its value and source; an override also shows the deployment default. Edit opens the validated setting form. `SystemSettings` validates an explicit list of settings and stores only overrides in `admin_settings`. Reading or applying settings never inserts defaults. Saving a value equal to the typed deployment default deletes its override. Explicit `false`, `0`, empty lists and a cleared optional URL can override nonempty defaults. Reset removes the row and restores the deployment default.

`EnvironmentConfigProxy` captures resolved Laravel configuration before the first overlay, so existing `config()` consumers receive effective values without changes to `env()` or `.env`. Locale changes also update `locale.default_language`; timezone and environment changes update Laravel's runtime state. `CacheDeploymentConfig` keeps database values out of `config:cache`, including calls through `optimize` or Artisan in PHP. Cached installations still read live overrides on boot, and resets do not require rebuilding the config cache.

Reload the browser after saving. Subsequent HTTP requests load overrides before service providers boot. Queue jobs refresh the configuration repository, but already constructed services and immutable domain configuration objects can retain their earlier values. Restart long-running workers after changing settings. Changing session encryption can invalidate existing sessions. An environment override changes runtime configuration, but does not select a different `.env` file or rerun the deployment bootstrap. Derived deployment values such as cookie names and cache prefixes remain unchanged.

The screenshots from the legacy administration include switches that have no equivalent in this version. WebAuthn, global feature switches and database/file translation switching are not added by these settings. Model capabilities remain configured in the model/tool sections; announcements remain in their own section.

The environment page shows selected effective configuration and masked secret status. Database/auth/mail/Redis/Reverb credentials, encryption keys, converter credentials and infrastructure changes remain deployment tasks; the panel does not write `.env` or restart services.

Permanent user-data deletion remains available through the existing CLI. The panel's account-disable action preserves data. Chat contents and keychains are not accessible to administrators.
