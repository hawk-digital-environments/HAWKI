# v%%VERSION%%

### What's New

[//]: # (- The main new features and changes in this version.)

### Quality of Life

[//]: # (- Improvements and enhancements that improve the user experience.)
- Room admins can now change a member's role directly in the member info dialog, instead of removing and re-adding the member.

### Bugfix

- `php hawki init` and `php hawki run -build` no longer run an unattended `composer update` when outdated packages are detected. Setup and build now only perform a deterministic `composer install`; upgrading packages is a deliberate decision. Previously the silent update could jump packages across major versions (e.g. `laravel/ai` 0.10 → 1.3), breaking the app with errors that first surfaced in unrelated commands like `models sync`.
- Self-service access token creation was unavailable in the profile view: it still checked the pre-2.5.0 `sanctum.*` config keys instead of the new `external_access.*` keys, so the AccessTokens panel never appeared even with `ALLOW_EXTERNAL_COMMUNICATION` and `ALLOW_USER_TOKEN_CREATION` enabled.
- **Security:** Fixed a privilege escalation in group chat invitations. Any room member (including viewers) could create an invitation for themselves with the `admin` role and accept it to become a room admin, or overwrite other users' pending invitations. Sending invitations is now restricted to room admins, invitation roles are validated, and accepting an invitation no longer changes the role of an existing member. Thanks to [Artur Chlebicki](https://github.com/Artur12555) for responsibly reporting this issue!
- The room controls are now fully restricted for room admins.
- Removing or leaving a room now deletes the member's pending invitations for that room, and leaving a room no longer fails halfway.
- An admin leaving a room no longer deletes the whole room when other admins remain. The last admin of a room can't leave it anymore, only delete it.
- Members can no longer change their own role, and the role of HAWKI can't be changed. Members can only be added to a room through invitations.
- Model enrichment (pricing, context windows, feature flags) stopped updating because LiteLLM discontinued their catalog API. Lookups always fell back to the static files. The catalog is now read from LiteLLM's GitHub file.

### Internals

- The `laravel/ai` version constraint is now `^0.10.1` (was `>=0.10.1`), so dependency updates stay within semver-compatible releases instead of silently allowing a major jump. Lock file refreshed to `laravel/ai` v0.10.3.
- LiteLLM catalog: downloaded once into a shared cache and filtered per provider, kept fresh via ETag revalidation every 5 minutes. During upstream outages cached data is served for up to 24 h, then the static files take over.
- The `static_llm_data` updater no longer deletes the old files when the catalog is unreachable.
- Added `RoomRepository` and `InvitationRepository` (`App\Services\Chat\Room\Repositories`). Room and invitation queries in `RoomService`, `InvitationController`, `StreamController`, the `roomAdmin`/`roomEditor` middlewares, the room broadcast channel and the `migrate:avatars` command now go through these repositories instead of Eloquent statics.
- Replaced the unused `POST /req/room/addMember/{slug}` endpoint, which could add members without a room key, with `POST /req/room/changeMemberRole/{slug}` (route name `web.roomEditMember` unchanged).

### Deprecation

[//]: # (- List of features or functionalities that have been deprecated in this version.)
