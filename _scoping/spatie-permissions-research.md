# Spatie permissions for HAWKI

Research date: 2026-09-15. Code inspected at `05b68e00`, including the current working tree. Research and proposed design only. No dependency or application changes were made, and no runtime security tests were performed.

## Recommendation

I recommend `spatie/laravel-permission` if HAWKI is extending permissions to tools and other user-facing features. Use it for role membership, permission assignments, and permission lookup through Laravel. Keep HAWKI's policies and safeguards around administering those assignments.

The existing custom implementation is small and already contains useful protections. Replacing it only to reduce code would have a modest payoff. Extending authorization across the application makes a shared Laravel-compatible permission system more useful. Spatie integrates permission checks with Laravel's Gate; HAWKI must still decide where to enforce them. [Spatie introduction](https://spatie.be/docs/laravel-permission/v8/introduction)

The immediate tool-use improvement is independent of the package decision: authorize the acting user when selecting tools and again before local tool execution.

## What the backend does today

The older [admin scope](admin-panel.md) describes a time before RBAC existed. The current code already implements it.

| Area | Current behavior and evidence |
| --- | --- |
| Versions | [composer.json](../composer.json) requires PHP `^8.3` and Laravel `^13.23`; the lockfile contains Laravel `v13.23.0`. Spatie Backup is installed, but Spatie Permission is not. |
| Permission lookup | [PermissionService](../app/Services/Admin/PermissionService.php) joins `role_user` and `role_permissions`, ignores permission strings outside the enum, and denies effective permissions to disabled or removed accounts. |
| Admin endpoints | [ResourceRepository](../app/Services/Admin/Repositories/ResourceRepository.php) delegates to `PermissionService::authorize()`, which requires both `admin.access` and the section permission. [ResourceCatalog](../app/Services/Admin/ResourceCatalog.php) maps tool administration to `mcp.manage`. |
| Administrative safeguards | [RoleGuard](../app/Services/Admin/RoleGuard.php) checks grantable permissions, protects the last administrator, and protects the acting administrator's access. [UserRepository](../app/Services/Admin/Repositories/UserRepository.php) checks the assigned permissions of target accounts even when those accounts are disabled. |
| Identity-provider mapping | [EmployeeTypeRoleSyncer](../app/Services/Admin/EmployeeTypeRoleSyncer.php) replaces employee-type-derived assignments while retaining manual assignments. |
| Resource ownership | [AiConvPolicy](../app/Policies/AiConvPolicy.php), for example, restricts conversation access and changes to the conversation owner. |
| Frontend | [ConnectionFactory](../app/Services/Frontend/Connection/ConnectionFactory.php) exposes effective permissions; [frontend permission checks](../resources/js/kernel/auth/permissions.ts) consume them. |
| Existing regression coverage | [AdminPanelTest](../tests/Feature/AdminPanelTest.php) covers section access, escalation, disabled administrators, mapping changes, and other admin behavior. These tests were inspected, not run for this research. |

This is already role-based access control. The choice is whether to keep maintaining its storage and lookup implementation as the permission vocabulary grows.

## The tool-use gap

The inspected tool path contains configuration and availability checks, but no acting-user permission decision:

- [AiToolPolicy](../app/Policies/AiToolPolicy.php) supplies an authenticated-user `viewAny` check. It has no tool execution rule.
- [ChatToolResolver](../app/Services/Ai/Agents/Implementations/Chat/ChatToolResolver.php) accepts frontend transfer strings and selects native tools, tools by capability, or tools by name.
- [LaravelToolResolver](../app/Services/Ai/Tools/LaravelAi/LaravelToolResolver.php) resolves against model tools or provider factories. Its methods do not check a user's tool permissions.
- [AgentRequestContext](../app/Services/Ai/Agents/Values/AgentRequestContext.php) contains provider, model, parameters, and usage type. It carries no acting user.
- [LaravelMcpTool](../app/Services/Ai/Tools/LaravelAi/LaravelMcpTool.php) checks offline status before calling MCP. It has no user authorization check.
- [AbstractTool](../app/Services/Ai/Tools/AbstractTool.php) validates arguments and limits calls, but does not authorize the actor.

There are also different availability rules across resolution branches. Capability lookup explicitly checks `active` and server status. Name lookup does not repeat those checks, although the [AiTool active scope](../app/Models/Ai/AiTool.php) normally filters database results. Explicit `native` selection uses the provider factory without the model-native-capability check used by `auto`. These are reasons to centralize eligibility, not proof that a normal request can execute any disabled tool. Confirm the actual bypass cases with integration tests before treating them as demonstrated vulnerabilities.

Granting `mcp.manage` means permission to configure tools today. It should not become the permission ordinary users need to run them.

## Proposed authorization structure

Use three cooperating parts:

| Part | Responsibility |
| --- | --- |
| Spatie | Store roles, grant permissions to roles, assign roles to users, answer permission checks. |
| Laravel policies and HAWKI services | Combine grants with account status, resource ownership, model eligibility, and tool-specific restrictions. |
| Backend entry points | Invoke those decisions before returning protected data or performing an action. |

Laravel supports Gates for general actions and policies for decisions about individual resources. Keep conversation ownership and room membership in policies. A broad permission should not bypass them. [Laravel authorization](https://laravel.com/docs/13.x/authorization)

### Protecting administration

Preserve the existing permission names and both requirements for an admin operation. Conceptually, after migration:

```php
// Account eligibility is enforced before these grant checks.
Gate::forUser($actor)->authorize('admin.access');
Gate::forUser($actor)->authorize('mcp.manage');
```

Keep `PermissionService` as a migration adapter so existing repositories, field redaction, frontend serialization, and scope guards retain their behavior. Its effective checks must still exclude disabled and removed users. Its `assignedPermissionsOf()` operation must continue returning a disabled target's grants for takeover-prevention checks.

Do not expose raw `assignRole()`, `syncRoles()`, or `syncPermissions()` calls directly to the admin API. Route assignment mutations through the existing grantability checks, transaction locking, last-administrator checks, and audit logging.

Avoid an unconditional super-administrator `Gate::before()` grant. That can allow an administrator through ownership policies as well as administrative permissions. Explicit grants match the current model better. Also keep stored permission names separate from resource policy abilities: a stored permission named `update` could interfere with an `update` policy. Use qualified names such as `models.manage`. [Spatie super-admin guidance](https://spatie.be/docs/laravel-permission/v8/basic-usage/super-admin)

Spatie registers its permission integration through a Gate callback. A disabled-account denial added elsewhere must run before any granting callback, or be enforced at the common application boundary. Verify callback ordering in the implemented configuration; adding a late policy check alone is insufficient. An ordinary permission miss should return `null` from a custom before callback so resource policies can still decide. [Spatie registrar source](https://github.com/spatie/laravel-permission/blob/8.3.0/src/PermissionRegistrar.php)

### Protecting tool use

Add a `ToolAuthorization` service and an explicit actor to the request context, supplied by trusted server code. For queued work, retain the initiating user's ID and reload that user and their current grants when the job executes. CLI/system tasks need an explicit service identity or narrowly defined internal authorization path. Missing HTTP authentication must never imply administrator access.

Start with a small, code-owned permission vocabulary. These names are proposals:

| Permission | Meaning |
| --- | --- |
| `tools.use` | General eligibility to use tools. |
| `ai.capabilities.web_search.use` | Eligibility for web search regardless of whether the provider or an MCP tool implements it. |
| `ai.capabilities.image_generation.use` | Eligibility for image generation where supported. |
| `tools.internal_search.use` | Eligibility for a restricted application tool or tool group. |
| Existing `mcp.manage` | Configure tools and MCP servers. |

Each tool maps to an explicit access rule. New or unmapped tools should remain unavailable until assigned a rule. Avoid silently granting every newly discovered MCP tool to all existing users. If individual tool grants become necessary, use stable server/tool identifiers rather than mutable display labels.

A tool-use decision should require:

1. An eligible actor with `tools.use` and the capability or tool grants required by the configured rule.
2. An allowed model and a tool assigned to that model, or explicitly enabled native capability support.
3. Enabled tool/provider/server configuration and appropriate availability. PHP tools need a separate branch because they have no MCP server.
4. Authorization for the actual data and action requested by the arguments, such as the selected conversation or document.

Enforce this decision at the following points:

| Point | Required behavior |
| --- | --- |
| Discovery | Filter tool lists, capability choices, related resources, and JSON:API includes. Apply query filtering before pagination; `viewAny` alone does not filter individual rows. |
| Resolution | Check every path: name, capability with explicit tool, `auto`, and `native`. Deny a forged selection before sending its definition to a provider. Select an authorized fallback tool rather than blindly taking the first capability match. |
| PHP/MCP execution | Use an authorized wrapper or common executor to check current grants and eligibility immediately before side effects. A standalone MCP event listener would miss PHP and native tools. |
| Provider-native execution | Authorize before sending the native tool configuration on every provider request. HAWKI cannot intercept each native operation inside the remote provider once that request is running. |
| Tool internals | Apply ownership, document access, argument validation, and appropriate downstream credentials. Permission to invoke a search tool does not imply access to every document it can search. |

Keep the authenticated initiating user distinct from the HAWKI bot that authors a group-chat response. The [stream controller](../app/Http/Controllers/StreamController.php) uses bot identity when handling group output; that must not become the authorization identity.

Client-supplied tool settings and model-generated arguments cannot select the authorization identity or grant permissions. Authorization failures must stop execution. If converted into a tool error response, they must not fall through to a network call or expose credentials.

## Migration details that matter here

### Compatibility and guards

Spatie's current compatibility table lists package majors 7 and 8 for Laravel 12/13 with PHP 8.3+. The inspected `8.3.0` release declares PHP `^8.3` and Illuminate `^12.0|^13.0`, matching this checkout's declared versions. Resolve it against the full lockfile and deployment PHP during implementation; this research did not run Composer dependency resolution. [Spatie prerequisites](https://spatie.be/docs/laravel-permission/v8/prerequisites), [8.3.0 package requirements](https://github.com/spatie/laravel-permission/blob/8.3.0/composer.json)

The current [User model](../app/Models/User.php) extends Laravel's authenticatable model and has no existing `roles()` or `permissions()` relationship to conflict with `HasRoles`. HAWKI's users have integer IDs, matching the standard migration's default assumption.

Use one permission guard, initially `web`, for the same HAWKI user across session and Sanctum authentication. `auth:sanctum` authenticates API requests; it does not require a second copy of every permission under a `sanctum` guard. Configure the model's permission guard explicitly and test both cookie and bearer-token requests. Retain the existing external-app restrictions and any token-ability checks. [Spatie multiple guards](https://spatie.be/docs/laravel-permission/v8/basic-usage/multiple-guards)

### Existing tables are not a drop-in match

The [administration migration](../database/migrations/2026_09_10_120000_create_administration_tables.php) defines a custom schema. Plan a data migration rather than running the [package's migration](https://github.com/spatie/laravel-permission/blob/8.3.0/database/migrations/create_permission_tables.php.stub) unchanged over it.

- HAWKI's `roles.name` is a display name; `roles.slug` is its stable identifier. Spatie uses `name` as the role identifier and adds `guard_name`. Preserve the public admin API's distinction through an adapter or custom Role model.
- HAWKI stores permission strings directly in `role_permissions`. Spatie stores permission records and links their IDs through `role_has_permissions`.
- HAWKI's `role_user` primary key includes `source`, allowing a user to hold the same role through both manual and employee-type assignments. Spatie's default effective membership table does not represent that provenance.
- Preserve role IDs or migrate every reference, including employee-type mappings and announcement `target_roles`. Update admin edit-version calculations, bootstrap commands, factories, and direct database queries too.

Recommended provenance design: retain a dedicated assignment-source table and project its distinct user/role pairs into Spatie's `model_has_roles`. All writers use one service, one transaction/locking strategy, and one reconciliation operation. Removing an employee-type assignment then removes effective membership only if no manual assignment remains. Do not run `syncRoles()` using only identity-provider roles.

For an additive rollout, use separate package table names while importing existing roles with stable IDs. The package supports table-name configuration. Compare effective permissions before switching reads. Avoid prolonged independent writes to two authorization stores. [Spatie configuration](https://github.com/spatie/laravel-permission/blob/8.3.0/config/permission.php)

### Preserve semantics and cache correctness

Keep the code-owned permission registry. The current service ignores stale database permission names; replacing it with unrestricted package reads would change that behavior. Seed supported permissions and remove or ignore retired grants deliberately.

Initially allow grants through roles only. Although Spatie supports direct user permissions, enabling them would require extending role-editing UI, grantability checks, and last-administrator calculations to account for another source of access.

Use package mutation methods where possible. Existing raw SQL writes will need explicit cache invalidation and user-relation refresh. Long-lived workers must not reuse a previous actor or a user's previously loaded grants. Test revocation using an already-loaded user instance as well as a fresh request. [Spatie cache guidance](https://spatie.be/docs/laravel-permission/v8/advanced-usage/cache)

Do not enable Spatie teams just to represent chat rooms. Start with global installation roles and retain room-membership policies. Revisit teams only if HAWKI needs roles scoped to genuinely separate organizations.

## Is this better than maintaining the custom system?

| Question | Assessment |
| --- | --- |
| Less routine authorization plumbing? | Yes. The package supplies role and permission models, assignment APIs, and Laravel integration. |
| Automatically safer backend? | No. Safety depends on complete enforcement and preserving HAWKI's existing safeguards. |
| Less HAWKI-specific code? | Somewhat. Ownership, assignment provenance, anti-escalation, auditing, and tool execution rules remain application code. |
| Lower cost for today's admin-only feature? | Uncertain. The custom lookup is short, and migration has real cost. |
| Better foundation for expanding tool permissions? | My recommendation is yes, provided HAWKI uses policies and a shared execution authorization service alongside it. |

## Suggested implementation order

1. Preserve the existing behavior with the admin regression suite. Add missing tests for last-administrator concurrency and simultaneous manual/derived membership as needed.
2. Add Spatie behind `PermissionService`; migrate assignments, preserve IDs and source provenance, and verify equivalent effective permissions for existing users.
3. Introduce trusted actor context and `ToolAuthorization`; use it for catalog filtering and every resolver branch.
4. Enforce authorization before PHP/MCP side effects and before native provider requests. Add argument-level resource checks to restricted tools.
5. Refresh frontend choices from effective authorization results. Remove obsolete permission SQL after all callers and mutation paths have moved.

Acceptance tests should cover unauthorized forged tool selections; native/auto/name paths; function tools without MCP servers; revoked grants and disabled users; model/tool deactivation; unchanged conversation ownership; external-app requests; metadata through relationships; and jobs running after the initiating user's access is revoked. Assert that denied requests make zero downstream calls.

Adopting Spatie is worthwhile for the planned expansion. The highest-value work is making authorization explicit across the full tool path, while retaining the protections HAWKI already has.
