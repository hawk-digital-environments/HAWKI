# Artisan Commands

Every custom HAWKI command. Run them inside Docker via `bin/env`:

```
bin/env artisan <command> [options]
```

Domain pages link here instead of duplicating command descriptions.

## AI Provider & Model

| Command | Aliases | Description |
|---|---|---|
| `ai:check-status` | `check:model-status`, `ai:models:check-status`, `ai:tools:check-status` | Iterates external AI resources and updates their online status in the database |
| `ai:models:list` | — | List all AI models registered in the database |
| `ai:config:sync` | `ai:models:sync` | Sync AI config files into the database |

See [AI Service Layer](../400-Domains/100-AI/index.md).

:::danger[DEPRECATED / LEGACY]
`ai:config:sync` and the static config file approach will be removed once the new admin panel lands. See [Providers & Adapters](../400-Domains/100-AI/110-Providers-and-Adapters.md).
:::

## AI Tools

| Command | Description |
|---|---|
| `ai:tools:sync` | Sync tools from config files into the database (deployment-time operation) |
| `ai:tools:assign` | Manage which AI models are allowed to use which tools |
| `ai:tools:configure` | Configure an AI tool's attributes (capability, description, active state) |
| `ai:tools:list` | List all available tools (function-call and MCP) |

See [Tools](../400-Domains/100-AI/130-Tools.md).

## MCP Server

| Command | Description |
|---|---|
| `ai:tools:mcp:add` | Add an MCP server, discover its tools, and assign them to AI models |
| `ai:tools:mcp:configure` | Configure an MCP server's attributes (URL, label, timeouts, API key, etc.) |
| `ai:tools:mcp:list` | List all registered MCP servers |
| `ai:tools:mcp:remove` | Remove an MCP server and all its associated tools |

See [MCP](../400-Domains/100-AI/140-MCP.md).

## External App

| Command | Description |
|---|---|
| `ext-app:create` | A wizard to create a new external app that provides HAWKI features in a third-party interface |
| `ext-app:list` | Lists all external apps that are currently registered in the system |
| `ext-app:remove` | Deletes a registered external app from the system |

External app integration (not yet fully documented — see the ExtApp classes in the codebase).

## Personal Access Tokens

:::note[Distinct from ext-app tokens]
These commands manage individual user Sanctum tokens (`ApiTokenService`). They are a separate mechanism from the ext-app OAuth-like flow.
:::

| Command | Description |
|---|---|
| `app:token` | Create or revoke Sanctum API tokens for a user |

## User Management

| Command | Description |
|---|---|
| `app:removeuser` | Removes User From Database |
| `hawki:update-avatar` | Update HAWKI AVATAR |
| `migrate:avatars` | Migrate profile and room avatars from old structure to new AvatarStorageService structure |

## Usage Analytics

| Command | Description |
|---|---|
| `usage:summarize-monthly` | Summarizes and cleans up usage records monthly |
| `usage:top-users` | Display top users with most prompt_tokens and completion_tokens this month |
| `app:fetch-user-records` | Developer diagnostic: dump raw usage totals |

## Announcements

| Command | Description |
|---|---|
| `announcement:make` | Create a new announcement Blade view |
| `announcement:publish` | Create a new announcement entry referencing a Blade view |

See [Announcements](../400-Domains/600-Announcements.md).

## File Storage

| Command | Description |
|---|---|
| `filestorage:cleanup` | Cleanup expired files from the storage |
| `filestorage:converter:types:list` | List all MIME types (or file extensions) supported by the active file converter |
| `check:storage` | Check Storage Connection for specified filesystem |

See [Storage & Files](../400-Domains/300-Storage/310-Storage-and-Files.md), [File Converter](../400-Domains/300-Storage/320-File-Converter.md).

## Frontend Migration

| Command | Description |
|---|---|
| `make:frontend-migration` | Create a new migration file adding a new frontend migration |

See [Frontend Migrations](../400-Domains/500-Frontend-Bridge/520-Frontend-Migrations.md).

## Development / Diagnostic

| Command | Description |
|---|---|
| `dev:ai:update-lite-llm-static-data` | Fetches and refreshes the static LiteLLM model data files from the LiteLLM API. Only available in the local environment |
| `dev:helper:repository` | Generates helper code for repository classes to improve IDE support |
| `dbg` | Debug command |

See [Repositories — IDE support](../200-Concepts/130-Repositories.md) for `dev:helper:repository`.

## Deprecated

| Command | Description |
|---|---|
| `app:list-gwdg` | Pings the GWDG provider API and prints model status. Superseded by `ai:check-status` |
