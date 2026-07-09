---
sidebar_position: 3
---

# Private AI Conversations

Private AI conversations (`AiConv`) are one-to-one conversations between a single user and the AI. They differ fundamentally from group rooms: there is no member list, no invitation flow, no room key distributed via the keychain, and no read receipts. Access control is enforced entirely by ownership — the `user_id` column on `AiConv` is the sole authority on who may read, write, or delete the conversation.

## The `AiConv` Model

`App\Models\AiConv` represents a private conversation thread.

| Field | Type | Description |
|---|---|---|
| `user_id` | int | Owner's user ID |
| `conv_name` | string | Display name, editable by the owner |
| `slug` | string | Unique URL-safe identifier, generated on create |
| `system_prompt` | string\|null | Optional custom system instruction for this conversation |

The slug is generated using `Str::slug(Str::random(16))` — a random 16-character base and no human-readable prefix, giving a stable but opaque identifier.

`AiConv` has a `messages()` relation to `AiConvMsg` (keyed on `conv_id`).

## The `AiConvMsg` Model

`App\Models\AiConvMsg` stores individual messages within a private conversation. Like group room messages, content is encrypted client-side.

| Field | Type | Description |
|---|---|---|
| `conv_id` | int | Foreign key to `ai_convs` |
| `user_id` | int | The user who wrote this message (or user 1 for AI responses) |
| `message_role` | string | `'user'` or `'assistant'` |
| `message_id` | string | Decimal position string, same scheme as group room messages |
| `model` | string\|null | AI model identifier for assistant messages |
| `iv` | string | AES-GCM initialisation vector |
| `tag` | string | AES-GCM authentication tag |
| `content` | string | Encrypted ciphertext |
| `completion` | string\|null | Additional completion metadata from the AI |
| `metadata` | json | `{ tools: [...], params: {...}, citations: [...] }` |

Note the `citations` key in `metadata` — private conversations support citation metadata that group room messages do not currently carry.

`AiConvMsg` has an `attachments()` morph relation, functioning the same way as group room message attachments but using `StoredFileCategory::PRIVATE`.

## `AiConvService`

`App\Services\Chat\AiConv\AiConvService` is the public service entry point for private conversation operations. It is injected with a `PrivateMessageHandler` and provides:

- `create(array $validatedData): AiConv` — creates a new conversation with `user_id = Auth::id()`
- `load(string $slug): array` — loads the conversation and its messages; enforces ownership check
- `update(array $requestData, string $slug): bool` — updates name or system prompt; enforces ownership
- `delete(string $slug)` — deletes all messages (via `PrivateMessageHandler::delete()`) and the conversation; enforces ownership

Ownership is checked by comparing `$conv->user_id !== Auth::user()->id`. Any mismatch throws `AuthorizationException`.

:::warning Known coding-standard violations
`AiConvService` currently uses `Auth::id()` and `Auth::user()` facades, calls `AiConv::create()` and `AiConv::where()` as direct Eloquent statics, and uses `Log::error()` instead of an injected `Psr\Log\LoggerInterface`.

These are confirmed violations of HAWKI's no-facades-in-services and no-Eloquent-statics-in-services rules. They are on the refactor list. Do not treat this service as a model to copy. See the [Technical Debt Register](../100-Architecture/300-Technical-Debt.md) for the full record.
:::

## `PrivateMessageHandler`

`App\Services\Chat\Message\Handlers\PrivateMessageHandler` handles the message lifecycle for private conversations. It extends `AbstractMessageHandler` and uses `StoredFileCategory::PRIVATE` for file attachment storage.

- `create(AiConv $conv, array $data, User $user): AiConvMsg` — creates the message record. When `$data['isAi']` is `true`, it forces `user_id = 1` (the AI user) and `message_role = 'assistant'`. Ownership is checked against `Auth::id()`.
- `update(AiConv $conv, array $data): AiConvMsg|null` — updates message content and reconciles attachments: adds newly included UUIDs (persisting them from temp storage), removes those no longer listed.
- `delete(AiConv $conv, array $data): bool` — deletes attachments and the message record.

## How Private Differs from Group

**No room key.** Group rooms use asymmetric key distribution: the room key is encrypted per-member and stored in the user keychain. Private conversations do not distribute any key. Instead, the client encrypts messages with a key derived from the user's own passkey material. The server enforces `user_id` ownership and never needs to know the key.

**No invitation model.** There is no `Invitation` record for private conversations. The conversation is created by the owner and never shared.

**No read receipts.** `AiConvMsg` has no `reader_signs` field. There is no other participant to track reading state for.

**`StoredFileCategory::PRIVATE` files.** Attachments in private conversations are stored under the `PRIVATE` category. The `StorageProxyController` checks `$attachable->user_id !== $this->currentUser->id` rather than room membership when serving files in this category.

**`AiConvAccessScope`.** `AiConv` queries are restricted by an `AiConvAccessScope` (not yet a contextual scope — it is enforced in service methods by explicit ownership checks in the current implementation).

## JSON:API Resources

Private conversations are exposed via:

- `ai-convs` resource — lists and manages `AiConv` records
- `ai-conv-msgs` resource — lists `AiConvMsg` records for a given conversation

Both resources are scoped to the current authenticated user. The `ai-conv-msgs` resource carries encrypted content — the frontend decrypts it before display.
