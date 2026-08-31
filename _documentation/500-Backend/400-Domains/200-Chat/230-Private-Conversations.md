# Private Conversations

Private AI conversations (`AiConv`) are one-to-one conversations between a single user and the AI. They differ fundamentally from group rooms: there is no member list, no invitation flow, no room key distributed via the keychain, and no read receipts. Access control is enforced entirely by ownership — the `user_id` column on `AiConv` is the sole authority on who may read, write, or delete the conversation.

## `AiConv` and `AiConvMsg`

`App\Models\AiConv` represents a private conversation thread. `App\Models\AiConvMsg` stores individual messages within it, encrypted client-side like group room messages (see [Encryption](../400-Encryption/410-Encryption.md)). Open the model classes for the field list; the load-bearing concepts:

- `AiConv` is identified by a slug generated via `Str::slug(Str::random(16))` — a stable but opaque identifier.
- `AiConvMsg` uses the same decimal `message_id` scheme as group room messages (see [Messages](./220-Messages.md)).
- `AiConvMsg.metadata` carries a `citations` key — private conversations support citation metadata that group room messages do not currently carry.
- `AiConvMsg` has an `attachments()` morph relation, functioning the same way as group room message attachments but using `StoredFileCategory::PRIVATE`.

## `AiConvService`

`App\Services\Chat\AiConv\AiConvService` is the public service entry point for private conversation operations. It is injected with a `PrivateMessageHandler` and provides create / load / update / delete, all enforcing ownership by comparing `$conv->user_id` against the current user. Any mismatch throws `AuthorizationException`.

:::warning Known coding-standard violations
`AiConvService` currently uses `Auth::id()` and `Auth::user()` facades, calls `AiConv::create()` and `AiConv::where()` as direct Eloquent statics, and uses `Log::error()` instead of an injected `Psr\Log\LoggerInterface`. These are confirmed violations of the no-facades-in-services and no-Eloquent-statics rules. They are on the refactor list — do not treat this service as a model to copy. See [Technical Debt](../../900-Technical-Debt.md).
:::

## `PrivateMessageHandler`

`App\Services\Chat\Message\Handlers\PrivateMessageHandler` handles the message lifecycle for private conversations. It extends `AbstractMessageHandler` and uses `StoredFileCategory::PRIVATE` for file attachment storage.

- `create(AiConv $conv, array $data, User $user): AiConvMsg` — creates the message record. When `$data['isAi']` is `true`, forces `user_id = 1` (the AI user) and `message_role = 'assistant'`. Ownership checked against `Auth::id()`.
- `update(AiConv $conv, array $data): AiConvMsg|null` — updates message content and reconciles attachments: adds newly included UUIDs (persisting them from temp storage), removes those no longer listed.
- `delete(AiConv $conv, array $data): bool` — deletes attachments and the message record.

## How private differs from group

- **No room key.** Group rooms use asymmetric key distribution: the room key is encrypted per-member and stored in the user keychain. Private conversations do not distribute any key. The client encrypts messages with a key derived from the user's own passkey material. The server enforces `user_id` ownership and never needs to know the key.
- **No invitation model.** There is no `Invitation` record. The conversation is created by the owner and never shared.
- **No read receipts.** `AiConvMsg` has no `reader_signs` field. There is no other participant to track reading state for.
- **`StoredFileCategory::PRIVATE` files.** The `StorageProxyController` checks `$attachable->user_id !== $this->currentUser->id` rather than room membership when serving files in this category (see [Storage & Files](../300-Storage/310-Storage-and-Files.md)).
- **`AiConvAccessScope`.** `AiConv` queries are restricted by an `AiConvAccessScope` — not yet a contextual scope, enforced in service methods by explicit ownership checks in the current implementation.

## JSON:API resources

Private conversations are exposed via `ai-convs` (lists and manages `AiConv` records) and `ai-conv-msgs` (lists `AiConvMsg` records for a given conversation). Both resources are scoped to the current authenticated user; `ai-conv-msgs` carries encrypted content the frontend decrypts before display.

Note: `ai-convs` is currently served through the legacy web routes (`routes/web.php`), not through the JSON:API v1 server.
