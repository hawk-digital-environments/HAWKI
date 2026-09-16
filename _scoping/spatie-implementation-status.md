# Spatie frontend integration implementation status

Implementation date: 2026-09-15. This records changes made from [the frontend integration plan](spatie-frontend-integration-plan.md) and [the permission research](spatie-permissions-research.md). Those input documents remain unchanged.

All six phases are complete. Final independent reviews confirmed the four backend and five frontend findings were fixed, with no remaining material blockers or introduced regressions identified.

## Implementation

| Phase | Implemented behavior | Evidence |
| --- | --- | --- |
| 1. Spatie migration | Role IDs and public role fields preserved; assignment provenance projects into Spatie memberships; existing admin, target-account, grantability, audit, last-administrator, conditional-write and account-status checks retained. | `SpatiePermissionsTest`, `SpatiePermissionMigrationTest`, `SpatieRoleConcurrencyTest`, existing admin tests. Independent phase 1 review found no blockers. |
| 2. Backend contracts | Permission catalog, role labels, approved tool-rule catalog, `access_rule`, filtered tool resources and explicit native model IDs. | `ToolAuthorizationTest` checks catalog metadata, role bootstrap, tool rules, pagination, relationship linkage and includes. |
| 3. Administration | Grouped translated permission selector, searchable grants, old ungrantable grants retained, manual and mapped role labels, approved tool-rule editor, stale editor cleanup. | Admin suites and browser checks; final command results below. |
| 4. Refresh lifecycle | Same-session refresh notification, one authorization refresh path, catalog reload on successful refresh, generation/identity fencing, session cleanup and a single refresh following protected denials. | Auth/tool suites; browser grant and revocation while retaining session and draft. |
| 5. Composer | Current authorized catalogs determine ordinary, automatic and native choices; selections and saved state reconcile after refresh/model changes; send validates selections and distinguishes an unsent draft from a durably accepted message. | Tool suite; browser revocation and temporary-offline checks preserve unsent drafts and block invalid sends. |
| 6. Backend enforcement | Trusted initiating actor captured in context, eager selection checks, every resolver branch authorized, PHP/MCP invocation checks, every native/provider dispatch checked, stable HTTP and stream errors. | 31 new feature tests with local probes and mocked gateways/clients, including zero downstream calls on denial. |

## Initial grants and rollout

The new tool migration grants no additional permissions to existing roles and initializes all existing/new tools to `unavailable`. An operator explicitly enables administrator delegation with `php artisan rbac:grant-tool-access admin web_search`, optionally repeating for `image_generation` and `internal_search`. The command holds the common role lock and records an audit entry. Administrators then grant permissions to user roles and publish approved rules on intended tools. On fresh installations the original admin bootstrap grants its full registered permission set, but tools still start unavailable.

[The RBAC runbook](../_documentation/500-Backend/800-Encryption-and-Security/300-RBAC.md#tool-access) documents the commands, exact additive contracts, execution boundaries, and rollback requirements. Deploy code/schema together with workers restarted; deploy the coordinated strict frontend schemas with these backend fields.

## Backend verification

All database checks below ran against isolated MySQL database `hawki_spatie_test_20260915`, never the development database. Tests using that database ran serially.

```bash
APP_ENV=testing DB_HOST=127.0.0.1 DB_DATABASE=hawki_spatie_test_20260915 \
CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync BACKUP_DISABLED=1 \
php vendor/bin/phpunit tests/Feature/ToolAuthorizationTest.php \
tests/Feature/AdminPanelTest.php tests/Feature/SpatiePermissionsTest.php \
tests/Feature/AdminApiRoutingTest.php \
tests/Unit/Services/Ai/Agents/Implementations/Chat/ChatToolResolverTest.php \
--no-coverage --do-not-cache-result
```

Result: 96 tests, 1,023 assertions, zero failures, one existing config-import skip. Targeted PHPStan passes for the changed tool, catalog, admin-repository, gateway and stream-controller code. The scoped analysis uses the repository configuration with `reportUnmatchedIgnoredErrors=false` to avoid reporting an unrelated globally configured ignore as unmatched in a partial run. PHP syntax checks passed for all 55 then-current changed/new PHP files.

A final targeted rerun after rejecting legacy agent deserialization passed 31 tests and 128 assertions. Tests cover missing actors, forged actor payloads, name/capability/automatic/native paths, PHP tools without MCP servers, MCP revocation and server replacement with zero client calls, already-loaded actors, disabled accounts, model/provider/tool disabling, removed model assignments, dedicated native grants, offline native catalog visibility, continued provider dispatch after revocation, filtered JSON:API relationships/includes, access-rule weakening protection, and audited bootstrap.

The parent independently ran the existing `tests/Unit/Services/Ai/Agents` and `tests/Unit/Services/Ai/Providers/Adapters` suites after the SDK entrypoint and provider-cache fixes: 445 tests and 823 assertions passed. The run used the isolated environment above; its log is `/tmp/hawki-spatie-parent-unit.log`.

## Browser verification

The parent agent used a separate local MySQL database `hawki_spatie_browser_20260915` with two authenticated browser sessions and no live provider requests. Confirmed:

- Grouped permission selectors and grantable states render in Administration.
- The explicit bootstrap command makes web-search grants available to the administrator.
- A restricted user cannot open an administration page directly.
- Granting tool access and focusing the restricted user's existing page refreshes tool choices.
- Revoking access removes selected automatic web search, shows one notice, and preserves the exact draft and editor focus without restarting authentication.
- Creating a role through the browser saves its name and slug correctly.
- Revoking `roles.manage` while a role-creation dialog contains unsaved input closes the dialog, removes protected rows, and shows access denied while retaining the authenticated user session.
- A rule-only change from `web_search` to `unavailable`, with unchanged user grants and unchanged tool enabled state, removes an explicit tool selection on refresh and preserves the draft/focus. Separately authorized native web search remains available.
- After reload, a temporarily offline selection remains visible, checked and disabled. The temporary-offline banner and Send hint explain its state, the draft remains intact, and sending is blocked. Browser mocks were restored afterward.
- Mounted admin browser scripts check scalar editing, permission-search counts, Space checkbox toggling, ArrowDown radio selection and focus, injected validation-error associations and focus, and Escape/discard focus restoration.
- The admin refresh regression preserves the exact editor node and draft when a same-permission refresh leaves the row unchanged. A changed row version closes the editor, announces the change and restores focus. A pending delete confirmation closes without executing the deletion. These script scenarios use browser-only mocked responses and make no server mutations; the scripts are in `tests/js/admin`.

## Frontend verification

Frontend command results reported by the implementing agents:

| Command | Result |
| --- | --- |
| `npm run test:admin` | 71 passing |
| `npm run test:admin:types` | Zero errors and warnings |
| `npm run test:tools` | 43 passing |
| `npm run test:auth` | 52 passing |
| `npm run test:routing` | 9 passing |
| `npm run test:search` | 70 passing |
| `npm test` | Final aggregate run: 245 passing, zero failures |
| `npm run check` | Final run passed with zero errors and warnings; accompanying TypeScript run exited 0 |

The parent reran aggregate `npm test` on the final files: all 245 tests passed (52 auth, 71 admin, 9 routing, 70 search and 43 tools). Its log is `/tmp/hawki-spatie-final-npm-test.log`. The admin scalar-field regression failed before its fix and passed afterward; browser role creation then saved successfully.

The final independent frontend recheck confirmed all five findings fixed, found no material introduced regressions, and confirmed the focused tests exercise the previously missing boundaries. The default-home Sol CLI reached its usage limit; the completed verdict came from the native Sol fallback (`frontend_final_sol`) without a `CODEX_HOME` override.

## Accepted-message boundary and limits

A denial during preflight or upload preserves the unsent draft. If the server durably accepted the message before a later authorization change, the client marks it accepted, clears the submitted draft and reports that the message was saved without an AI response. It does not replay the request and never publishes its result into a different actor's session.

No real provider-native or external MCP operation was sent during verification. Assistive-technology device verification was not performed. Already-running provider-native operations cannot be recalled after dispatch. Group generation still uses the existing shutdown callback instead of a persistent queue; the captured context retains the initiating actor and checks their current permissions at execution. SDK agent serialization, queue/broadcast and alternate prompt/stream entry points now fail closed with `TOOL_ACCESS_DENIED`. Future queued producers must persist validated input and the trusted initiating actor ID, reconstruct context/selections in the worker, and use HAWKI send/sendStreaming.

## Local connection failure resolved

The user's existing local database `db` still had both new migrations pending, causing connection refresh to fail on the missing `model_has_roles` table. Following the user's report, the parent authorized applying the implementation locally. The application container was paused, the database was backed up to `/tmp/hawki-spatie-local-backup-3fy4_yfe/db-before-spatie.sql` in a private directory with file mode `0600`, and only the Spatie and tool-rule migrations were applied. Both now report `Ran`, and the existing user count remains two. The container was resumed, package discovery refreshed, and its permission cache reset. An in-process authenticated kernel request as existing user 2 to `/api/hawki/v1/connections/hawki` returned HTTP 200 with resource type `connections`. Verification used an array session driver and created no token or persistent login. No test suite ran against this database.

## Backend review corrections

The independent backend review identified four defects in alternate SDK entry points, MCP client caching, provider-driver caching, and stale capability mapping. Its bounded recheck confirmed all four fixes and found no remaining blockers; the report is `/tmp/hawki-spatie-sol-backend-recheck.md`.

The MCP reconfiguration regression failed before the fix. In the same service lifetime, a fresh tool now obtains a fresh client after any change to endpoint, credentials, transport, additional options or timeouts. Unchanged configurations reuse the client; obsolete cached entries are replaced. The corresponding provider regressions also failed before the fix: consecutive provider configurations using one adapter now produce independent drivers with their own credentials. Capability and mapped-capability changes invalidate previously resolved selections before local execution.

Tests reject all inherited SDK dispatch methods for native and local tools with the authorization registry cleared, reject serialization of real SDK invoke/broadcast jobs and deserialization of legacy agents, and reject a queued invoke after revocation. These are deliberately unsupported entry paths; supported send/sendStreaming and group callbacks retain the checked HAWKI path. Discovery coverage now puts a denied row first in the requested sort order and checks denied direct fetches plus MCP-server linkage, related resources and includes.

A separate existing schema defect was observed while testing options: `mcp_servers.additional_config` is a MySQL JSON column, while the model applies an encrypted JSON cast. Saving nonempty options through that cast fails with MySQL error 3140 because ciphertext is not JSON. This RBAC change does not migrate that unrelated column; the cache regression changes its raw stored value with a mock client factory to verify fingerprint invalidation without exercising transport configuration or making network calls.
