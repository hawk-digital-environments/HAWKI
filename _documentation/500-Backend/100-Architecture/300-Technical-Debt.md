# Technical Debt Register

The violations below were confirmed against `_documentation/6-Contributing.md` as of v2.5.0. Items are tagged as `pre-refactor rough edge` (already known, tracked) or `new finding` (identified in the v2.5.0 audit). Every item carries a rule reference so the writer knows what standard it breaks.

:::note[Audience]
This article is for contributors and reviewers. Operators and plugin authors do not need to read it.
:::

**Do not copy any pattern in this list.** If you need to work in a listed class, follow the standard patterns for your new code even if the surrounding code does not.

---

## HIGH — Active violations in non-deprecated code

These are targeted for the v2.x refactor cycle.

| Location | Violation | Rule | Status |
|---|---|---|---|
| `app/Services/Profile/ProfileService.php` | `Auth::user()`, `Auth::logout()` facade calls; `app(AvatarStorageService::class)` helper; `Session::put()` from a service; `ServiceLocatorTrait` in a service (not an API Resource) | No facades in services; no session/HTTP access; `ServiceLocatorTrait` reserved for API Resources | Pre-refactor rough edge (explicitly flagged in Coverage Gaps) |
| `app/Services/Profile/PasskeyService.php` | `Auth::user()` facade; `PasskeyBackup::where()` and `PasskeyBackup::updateOrCreate()` direct Eloquent statics; `Session::get()` in a service | No facades; no Eloquent statics in services; no session access | Pre-refactor rough edge (same Profile domain) |
| `app/Services/Chat/AiConv/AiConvService.php` | `Auth::id()` / `Auth::user()` facade; `AiConv::create()`, `AiConv::where()` direct Eloquent statics; `Log::error()` facade for logging | No facades; no Eloquent statics; no `Log::` facade (inject `LoggerInterface`) | New finding |
| `app/Services/Chat/Room/Traits/RoomFunctions.php`, `RoomMembers.php`, `RoomMessages.php` | Trait-based service decomposition (the `// ❌ Bad` anti-pattern); `Auth::id()`, `Auth::user()`, `Log::error()`, `app(AvatarStorageService::class)` inside those traits | No trait splitting of services; no facades in services | Trait split: pre-refactor rough edge (flagged in Chat section). Facade/`app()` inside traits: new finding |
| `app/Services/Announcements/AnnouncementService.php` | `Auth::user()` facade; `Session::put()` in a service; `now()` direct time construction | No facades; no session access; never use `now()` in services | New finding |

---

## MEDIUM — Violations in deprecated or self-acknowledged code

Mostly scheduled replacements or code with explicit self-deprecating comments. Lower urgency but still violations of documented standards.

| Location | Violation | Rule | Status |
|---|---|---|---|
| `app/Services/Chat/Message/Handlers/GroupMessageHandler.php`, `PrivateMessageHandler.php` | `Auth::id()` / `Auth::user()` facade calls inside message handler services | No facades in services | New finding |
| `app/Services/Ai/UsageAnalyzerService.php` | `Auth::user()` facade; multiple `Carbon::now()` calls; `UsageRecord::selectRaw()`, `UsageRecord::whereMonth()` direct Eloquent statics | No facades; never use `Carbon::now()`; no Eloquent statics | `@deprecated` class, scheduled for replacement. Violations are partly why it is deprecated |
| `app/Models/Room.php` | `app(UserKeychainRepository::class)`, `app(GroupMessageHandler::class)`, `app(AvatarStorageService::class)` inside model methods; business logic (`addMember`, `removeMember`, `deleteRoom`) in the model | No `app()` in models; no business logic in models | Self-acknowledged — the file has a `"Please don't do it like this"` comment at the offending lines |
| `app/Models/User.php` | `now()` called directly inside model methods | Never use `now()` in services / value objects (models cannot use constructor injection, making this irresolvable without restructuring) | Known structural limitation |
| `app/Http/Controllers/RoomController.php` | `User::find(1)` direct Eloquent static; authorization + business logic inline in two methods (`getAttachmentUrl`, `deleteAttachment`); `Auth::id()` / `Auth::user()` facade | Controllers call one service method; no direct DB access | New finding |
| `app/Http/Controllers/StreamController.php` | Inline validation (not delegated to `FormRequest`); 130-line `handleGroupChatRequest` private method mixing domain logic, encryption, model queries, and broadcasting; `Room::where()` direct Eloquent static | Delegate validation to `FormRequest`; no business logic in controllers | New finding |

---

## LOW — Minor style and type issues

Style/type cleanup with no correctness impact.

| Location | Violation | Rule | Status |
|---|---|---|---|
| `app/Services/Chat/AiConv/AiConvService.php`, `app/Services/Chat/Room/Traits/RoomFunctions.php` | `Log::error()` facade instead of injected `Psr\Log\LoggerInterface` | No facades in services; inject `LoggerInterface` via constructor | New finding |
| `app/Services/Mail/MailService.php` | `sendWelcomeEmail($user)` missing parameter type and return type | Always declare parameter and return types | New finding |
