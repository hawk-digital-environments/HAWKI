# Spatie integration plan for the new frontend

Status: proposed implementation plan, 2026-09-15. Based on the current Svelte frontend and the [backend research](spatie-permissions-research.md). This document does not implement the changes.

## Intended result

Administrators manage roles, assign users, and control tool access through the existing admin plugin. Users see the tools they are permitted to use. Changes to access update open pages and composer selections without requiring a full reload or unnecessarily restarting authentication.

Spatie runs in Laravel. The frontend continues consuming HAWKI's JSON:API resources and checking `app.can(permission)`. It does not need a Spatie JavaScript package or knowledge of Spatie's tables and guards.

Scope includes the backend contracts and enforcement necessary to make these frontend behaviors reliable. Preserve existing admin permissions, identity-provider mapping, resource ownership, and authentication/keychain flows. Keep direct user permission grants out of the first release; grant permissions through roles.

## What already exists

| Existing implementation | Integration approach |
| --- | --- |
| [Connection schema](../resources/js/app/schemas/resources/connections.schema.ts) exposes `userinfo.permissions` | Preserve the string-array contract. Change its backend source to Spatie through the existing adapter. |
| [Permission helper](../resources/js/kernel/auth/permissions.ts) and `app.can()` | Keep this as the frontend permission interface. Avoid role-name checks in components. |
| [Admin routes](../resources/js/plugins/admin/routes.ts), [module visibility](../resources/js/plugins/admin/AdminModule.ts), and [section definitions](../resources/js/plugins/admin/sections.ts) | Preserve `admin.access` plus each section's permission. |
| [AdminPage](../resources/js/plugins/admin/components/AdminPage.svelte) | Already hides section content reactively after revocation. Extend cleanup of pending editor/action state. |
| Role, user, and mapping pages | Extend existing editors. Each already requests a connection refresh after a successful write. |
| [AiToolStore](../resources/js/plugins/core/stores/AiToolStore.svelte.ts) | Load authorized tool/capability choices and handle refresh races and session cleanup. |
| [ToolSlice](../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/ToolSlice.svelte.ts) | Reconcile active, temporarily disabled, and restored selections against current choices. |

## Phase 1: migrate backend permissions while preserving the frontend contract

Implement the backend migration described in the research first:

1. Add Spatie, its models/configuration, and purpose-built migrations.
2. Keep `PermissionService` as the adapter for effective permissions, assigned target permissions, and admin authorization.
3. Import existing role permissions and preserve role IDs or explicitly translate every reference. Preserve manual and employee-type assignment sources separately from effective membership.
4. Keep the role/user/mapping endpoints and their field names stable. In particular, `roles` on an admin-user record remains manual assignments and `mapped_roles` remains derived assignments.
5. Preserve last-administrator, grantability, disabled-account, audit, and `If-Match` protections. Adapt bootstrap commands and all assignment writers to the same mutation service.

The frontend must continue receiving `name`, `slug`, `description`, `is_system`, and `permissions` for role records. Map Spatie's internal role name to this existing public representation.

**Completion condition:** the existing admin interface behaves the same with Spatie-backed data. Admin endpoint and frontend access tests pass without broadening any role's access.

## Phase 2: define the additional frontend contracts

Extend existing resources where possible. Field names below are proposed and should become explicit schema changes in both PHP and Zod.

| Resource | Contract |
| --- | --- |
| `connections/hawki` | Keep `userinfo.permissions: string[]` as effective grants for the authenticated user. Do not mix in role metadata or Spatie guard names. |
| `admin-roles` collection metadata | Add a permission catalog with `{name, group, title_label, description_label, grantable}` entries. The catalog comes from the backend's code-owned registry. |
| `admin-users` | Keep `roles` and `mapped_roles`. Resolve role labels through authorized admin metadata. If target-account restrictions need action hints, return explicit allowed actions computed by the backend. |
| `admin-tools` | Add an access-rule field, for example `access_rule`, with choices from an approved rule catalog. Labels explain who may use the tool. New tools default to unavailable until a rule is selected. |
| `ai-tools` | Return only tools the actor may discover/use under policy, retaining status information needed to show temporary unavailability. Filter relationship endpoints and includes too. |
| `ai-tool-capabilities` | Return authorized capability choices. Add `native_model_ids: string[]` listing model resource IDs for which this actor may select that capability natively. An empty array means no native selection. |

The explicit native list prevents a capability authorized through MCP from accidentally enabling provider-native execution merely because the model advertises support. `hasNativeCapabilityFor(model)` must require that list membership as well as model configuration. Update the strict capability schema before the backend emits the new field; missing native authorization data must deny native selection during rollout.

For ordinary tool choices, the backend-filtered tool records plus model assignments determine eligibility. The frontend should not reproduce the server's mapping from roles to tools. Authorization and online status remain separate concepts.

Access-rule editing changes who receives access. Keep ordinary tool configuration under `mcp.manage`, and initially require both `mcp.manage` and `roles.manage` to edit the access rule. The backend must also prevent an actor from weakening a rule they are not allowed to change. Disable or omit this editor field when those requirements are not met.

**Completion condition:** schemas and contract tests cover the new metadata, manual/derived roles, empty tool lists, and native eligibility. The package's storage layout is absent from frontend payloads.

## Phase 3: improve the existing administration pages

### Roles

Extend [AdminRoles](../resources/js/plugins/admin/pages/AdminRoles.svelte) with a grouped permission selector using the catalog from phase 2. Group administrative permissions separately from tool-use permissions. Show translated names and short descriptions; allow searching.

Only grantable permissions can be newly selected. Existing permissions that the actor cannot grant must remain visible, and the backend must reject unauthorized modification of the target role. Do not silently strip permissions because a checkbox is missing from the editor. Preserve built-in role slug immutability.

### Users and identity-provider mappings

Extend [AdminUsers](../resources/js/plugins/admin/pages/AdminUsers.svelte) to show manual assignments as editable and employee-type-derived assignments as read-only with a source label. If the same role has both sources, show both. Removing its manual assignment must leave its derived access intact.

Keep [AdminMappings](../resources/js/plugins/admin/pages/AdminMappings.svelte) as the place to edit employee-type mappings. Explain the resulting role assignment, refresh affected data after saving, and preserve existing conditional-write handling.

### Tools

Extend [AdminTools](../resources/js/plugins/admin/pages/AdminTools.svelte) with an access-rule column and editor control. Use labels such as "Users with web search access" and "Unavailable to users". Keep the tool's enabled state, model assignment, and access rule visibly distinct.

The role editor grants the rule's associated permissions; the tool editor associates a tool with an approved rule. Avoid a second independent role-to-tool assignment system in the UI.

Use existing form controls where possible. New grouped controls need keyboard operation, field labels, clear error association, and reliable focus restoration. Add English and German translations. Permission codes can be secondary administrative detail; ordinary chat users should see plain-language feedback.

**Completion condition:** an authorized administrator can configure a role, assign it manually or through a mapping, and configure a tool's approved access rule through the existing pages. A less privileged administrator cannot broaden access through those controls or forged requests.

## Phase 4: refresh authorization without disrupting the session

There is an important distinction in the current client:

- [ConnectionHandle](../resources/js/kernel/client/connection/ConnectionHandle.svelte.ts) updates the existing connection object when only permissions change, but emits no event for that `connectionUnchanged` result.
- [ClientExtension](../resources/js/kernel/client/ClientExtension.svelte.ts) uses `connectionChanged` for identity/authentication transitions and can navigate into authentication flows.
- [connectionRefresher](../resources/js/kernel/client/connection/connectionRefresher.ts) already refreshes on focus, visibility changes, and every 15 minutes.

Add a typed successful-refresh notification for the same authenticated user, or an equivalent internal subscription, separate from `connectionChanged`. Capture the previous permission set before mutating the connection object if a diff is needed. A permission-only update must not emit `sessionLost`, clear chat keys, or initiate the handshake.

Use one coordinator for these operations:

1. Publish current effective permissions into the existing reactive connection.
2. Recheck open admin pages and actions. Clear inaccessible rows, close invalid editors/confirmations, and cancel or discard pending reads. Never let regaining access revive an old confirmation.
3. Refresh loaded model/tool catalogs, then reconcile composer selections.

Refresh catalogs after successful connection refreshes even if permission strings are unchanged: administrators may change a tool rule or disable a tool without changing the user's grants. Start with the existing focus/timer refresh. Real-time push invalidation is optional future work, not a first-release dependency.

Coalesce refreshes and tag requests with the current actor and a local request generation. Discard late results from an earlier user or refresh. Explicitly clear tool/model authorization data on session loss. `AiToolStore.loadData()` currently returns early for unauthenticated users without clearing its previous arrays.

After a protected operation returns `403`, display the denial and trigger at most one coordinated refresh. Do not automatically replay the mutation or turn every resource-level `403` into a logout. Preserve existing session handling for `401`/`419`; denial of the connection resource can still indicate a disabled account.

**Completion condition:** access changes update open pages and tool choices after refresh, with no authentication restart for a permission-only change. A late request cannot restore revoked or previous-user data. Until the next refresh, backend enforcement rejects stale choices immediately.

## Phase 5: enforce current choices throughout the composer

Update [aiToolStoreData](../resources/js/plugins/core/stores/aiToolStoreData.ts) to combine authorized tool records, authorized native model IDs, model configuration, and availability. Keep this logic behind the store's existing choice helpers.

Update [ToolSlice](../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/ToolSlice.svelte.ts) and [toolSliceData](../resources/js/plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.ts):

- Resolve selections against the current catalog when adding, enabling, restoring checkpoints, and loading historical transfer strings.
- Rebuild existing selections after catalog updates instead of keeping wrappers around stale tool objects.
- Remove unauthorized selections from both active and temporarily disabled registries. Changing edit mode must not restore revoked access.
- Validate the chosen inner tool as well as its capability, including `auto` and `native`.
- Preserve valid settings and the message draft. If access disappears, remove the affected selection and show one concise notice. Keep temporary offline/model conflicts distinguishable from revoked permission.

Update [ChatTransport](../resources/js/plugins/core/modules/chat/transport/ChatTransport.ts) to use one validated selection snapshot for every send and regeneration path. It currently serializes selections in multiple places. If revalidation changes the requested selection at send time, keep the draft and return control to the user rather than silently sending a different request or retrying generation.

When the authorized catalog is refreshing or unavailable following a known access change, disable submission with affected tool selections until they are revalidated. Tool-free chat can continue if its own requirements are met. Model changes must also revalidate selections.

The tool menu hides unauthorized entries. A previously selected entry that becomes unauthorized produces a generic access-change notice, without disclosing newly restricted tool metadata. Loading states must not briefly show an unfiltered catalog.

**Completion condition:** no composer path serializes an unauthorized or stale selection. Saved drafts, retries, model changes, and disabled-tool restoration behave consistently.

## Phase 6: complete backend tool enforcement and error handling

This phase can run alongside frontend phases 3–5, but must ship before enabling restricted tool access in production.

- Carry the trusted initiating actor through the agent request context, including group chat and background work.
- Invoke shared tool authorization in every resolver branch. Authorize PHP/MCP execution again before side effects and authorize native configuration before each provider request.
- Apply the same rules to direct API callers and catalog relationships. Retain argument-level ownership checks inside tools.
- Reject invalid selections before opening a stream when possible. For revocation discovered during a stream, emit a defined access-denied stream error and stop the denied operation; an already-open stream cannot become a new HTTP `403` response.
- Return stable error codes for tool denial and unavailable selections, with translated frontend messages. A denial must not automatically retry generation, silently choose another tool, or cause a downstream call.

**Completion condition:** editing the frontend or forging a request cannot bypass the restrictions. Denied PHP/MCP invocations make zero downstream calls. Native authorization is enforced before dispatch; already-running provider-native operations cannot be retroactively intercepted by the frontend.

## Verification and rollout

Extend existing suites rather than introducing a new test framework:

| Coverage | Location / approach |
| --- | --- |
| Permission contract, route combinations, live revocation | `tests/js/admin/access.test.ts`, `tests/js/auth/auth-schema.test.ts`, `tests/js/auth/connection.test.svelte.ts` |
| Editor metadata, save/refresh handling, source labels, stale versions | Existing admin schema/form/workspace suites plus focused component tests |
| Tool catalog and selection lifecycle | Add tool/composer tests using the existing Node/Svelte test registration; add an explicit package script so CI runs them |
| Backend invariants and forged selections | Extend `AdminPanelTest` and add feature tests for catalog filtering, execution denial, native paths, and revocation |
| Browser behavior | Test an administrator and restricted user in separate sessions: direct URLs, role changes, focus refresh, open dialogs, saved drafts, native choices, and account switching |

Run `npm run test:admin`, `npm run test:auth`, `npm run test:routing`, `npm run test:admin:types`, the new tool/composer script, and `npm run check` as applicable. Run affected backend feature/unit suites and static analysis. Include keyboard/focus and status-message checks for changed controls. These are implementation acceptance checks; none were run merely to create this plan.

Suggested reviewable change sequence:

1. Spatie migration and compatibility adapter, preserving current frontend behavior.
2. Permission/catalog contracts and complete backend tool enforcement.
3. Authorization refresh lifecycle and error handling.
4. Admin editor improvements and permission-aware composer behavior.

Keep new response fields additive and coordinate strict-schema changes with deployment. Define initial tool grants explicitly before enabling enforcement. Retain old assignment data until migrated grants and source reconciliation are verified. A rollback must not leave a frontend advertising restrictions that the active backend does not enforce.

## Definition of done

An administrator can give a role web-search access, assign it to a user, and see that user's tool menu update after refresh. Removing the access invalidates an already-selected tool and prevents forged execution requests. Administration still requires the existing section grants, manual and derived roles remain distinct, and permission changes leave the user's authenticated session and chat keys intact.
