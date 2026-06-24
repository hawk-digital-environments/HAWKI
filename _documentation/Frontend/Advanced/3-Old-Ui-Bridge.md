# Old UI Bridge

The HAWKI chat UI is being progressively rewritten from vanilla JS to Svelte 5. During this transition, new Svelte components cannot directly call legacy JS functions and legacy code cannot access Svelte reactive state. `OldUiBridge` and `OldUiMessageHistory` are the two typed communication points between the two layers — typed event buses that both sides subscribe to and trigger.

Source files:
- `resources/js/oldUi/OldUiBridge.svelte.ts`
- `resources/js/oldUi/OldUiMessageHistory.svelte.ts`

## Why It Exists

Legacy HAWKI chat code is a single large JavaScript module that owns conversation loading, message sending, model selection, and export. The Svelte rewrite introduces components (the composer, toast notifications, mode handling) that need to interact with all of these without being coupled to the legacy implementation details.

Rather than having Svelte components reach into legacy globals or having legacy JS import Svelte stores directly, all cross-layer communication passes through the bridge. This keeps the dependency boundary explicit and typed, and makes it straightforward to remove the legacy side once the rewrite is complete — only the bridge bindings need to change.

## Architecture

Import the singletons — do not instantiate the classes yourself:

```ts
import { oldUiBridge } from '$lib/oldUi/OldUiBridge.svelte.js';
import { oldUiMessageHistory } from '$lib/oldUi/OldUiMessageHistory.svelte.js';
```

### The Rule

The bridge is the **only** sanctioned way for new Svelte code to talk to legacy code and vice versa.

- Do not call legacy functions directly from Svelte components.
- Do not reach into Svelte stores or Svelte context from legacy JS.
- Do not bypass the bridge by importing legacy modules into Svelte or Svelte stores into legacy modules.

## Events: Legacy → Svelte

These are events triggered by the legacy layer. Svelte components register handlers via the `on*` methods. Each `on*` method returns an unsubscribe function — call it during `onDestroy` or equivalent cleanup.

### `onClearActiveConversation(handler)`

Triggered by the legacy layer when the currently active conversation is cleared (e.g. the user navigates away or the conversation is deleted). The Svelte composer should reset all its state — clear the input, cancel any pending mode, discard draft content.

Payload: none (`void`).

### `onLoadSystemPrompt(handler)`

Triggered when the legacy layer wants to push a new system prompt into the Svelte composer (e.g. after a conversation loads). The composer should replace its current system prompt field with the received value.

Payload: `string` — the system prompt text.

Note: this is distinct from `onActiveConversationSystemPromptUpdate`, which goes in the opposite direction (Svelte → legacy). `onLoadSystemPrompt` is legacy → Svelte only.

### `onLoadInitialModel(handler)`

Triggered once after a conversation loads, passing the AI model that should be selected by default. The Svelte model selector should apply this value on mount. It fires before the user has had a chance to interact, so treat it as an initialization event, not a user-driven selection.

Payload: `AiModel` — the full model object.

### `onEnterMode(handler)`

Triggered when the legacy layer requests that the composer enter a named mode. Modes are defined in `ComposerModeRegistry`; examples include `edit` (editing a previous message), `thread` (replying in a thread), and `regen` (regenerating a response). The `data` field carries mode-specific context — its type is inferred from the registry.

The handler signature is generic over the mode name:

```ts
oldUiBridge.onEnterMode((mode, data) => { /* ... */ });
```

The bridge guards this trigger — it will not fire if a message send is already in progress.

Payload: `{ mode: ComposerModeWithIs, data: unknown }` (narrowed by the generic).

### `onExitThread(handler)`

Triggered when the legacy layer requests that thread mode be exited. The Svelte composer should return to its default state. The bridge suppresses this event while a message send is in progress.

Payload: none (`void`).

### `onSendToast(handler)`

Triggered by any code (legacy or Svelte) that wants to display a toast notification. The Svelte toast component listens here and renders the message.

Payload: `{ message: string, type: 'success' | 'error' | 'info' }`.

### `onExitMode(handler)`

Triggered when the legacy layer (or Svelte via `triggerExitMode`) signals that the current non-default mode has been exited. The handler receives the state of the mode that was active, allowing cleanup or undo if necessary.

Payload: `ComposerModeWithIs` — the mode that was exited, including its `is` type narrowing helpers.

### `onActiveConversationSystemPromptUpdate(handler)`

Triggered when Svelte calls `updateActiveConversationSystemPrompt`. This is the legacy side's entry point for receiving system-prompt changes initiated from within the composer.

Payload: `string` — the updated system prompt.

### `onCurrentChatModelIdUpdate(handler)`

Triggered when Svelte calls `updateCurrentChatModelId`. The legacy model selector listens here to stay in sync with the composer's model state.

Payload: `string | null` — the new model ID, or `null` to clear the selection.

### `onContextReady(handler)`

Triggered once when the Svelte composer mounts and calls `triggerContextReady`. If the composer is already ready at the time `onContextReady` is called, the handler fires immediately (the bridge caches the ready state). Legacy code uses this to defer any initialization that requires the composer to be present.

Payload: none (`void`).

### `onSendMessage(contextType, handler)`

Triggered when the Svelte composer calls `triggerSendMessage`. The handler only fires if the payload's `contextType` matches the registered `contextType` — this lets different legacy handlers cover room conversations vs. AI conversations without filtering manually.

The handler is async. It receives `OldUiSendMessagePayload`, which includes the message text, model, system prompt, tools, attachments, parameters, and control callbacks (`setResponse`, `setResponseFailed`, `waitForResponse`). See the `OldUiSendMessagePayload` interface for the full shape.

### `onOpenChat(handler)`, `onNewChat(handler)`, `onRenameChat(handler)`, `onDeleteChat(handler)`, `onLeaveRoom(handler)`

Navigation and conversation management events triggered by Svelte UI actions. The legacy layer registers these to perform the underlying operations (routing, API calls, state resets). Each is suppressed while a message send is in progress.

- `onOpenChat` — payload: `string` (conversation slug)
- `onNewChat` — payload: none
- `onRenameChat` — payload: `{ chatSlug: string, newName: string }`
- `onDeleteChat` — payload: `string` (slug)
- `onLeaveRoom` — payload: `string` (slug)

### `onExportTrigger(handler)`

Triggered when Svelte calls `triggerExport`. The legacy layer handles the actual export.

Payload: `OldUiExportType` — one of `'print' | 'pdf' | 'word' | 'json' | 'csv'`.

### `onOpenRoomControlPanel(handler)`, `onMarkRoomMessagesAsRead(handler)`

Room-specific events. The legacy layer registers these to open the control panel or mark messages read for the given room slug.

Payload: `string` (room slug) for both.

### `onImproveMessage(handler)`

Async event triggered when the composer requests AI-assisted message improvement. The handler receives the draft message and current system prompt, and must return the improved message text as a string. The improved text is passed back to the composer via the resolved promise from `triggerImproveMessage`.

Payload in: `{ message: string, systemPrompt: string }`. Return value: `string`.

### `onPreviewAttachment(handler)`, `onDownloadAttachment(handler)`, `onDeleteAttachment(handler)`

Attachment lifecycle events. The Svelte attachment UI triggers these; the legacy layer handles the underlying file operations (preview modal, download, API deletion).

Payload: `OldUiFileData` — `{ uuid, name, mime, type, url, category }`.

## Calls: Svelte → Legacy

These methods are called from Svelte components and Svelte stores. The legacy layer registers the corresponding `on*` handlers.

### `triggerSendMessage(payload)`

Sends the composed message through the legacy pipeline. This is an async method that sets an internal `isSendingMessage` flag for its duration. While the flag is set, several other triggers (`triggerExitThread`, `triggerEnterMode`, `triggerExitMode`, `triggerOpenChat`, etc.) are suppressed to prevent state corruption mid-send.

The bridge wraps the payload's `waitForResponse` callback to automatically bind any `AbortController` provided by the legacy layer (via `bindAbortController`) to the response tracker, so that `response.abort()` works correctly.

### `triggerContextReady()`

Called by the Svelte composer once on mount. It sets an internal `isContextReady` flag and fires all registered `onContextReady` handlers. Any handler registered after this point fires immediately on registration.

### `triggerExitMode(oldState)`

Called by the Svelte composer when it exits a non-default mode (e.g. the user cancels an edit). Passes the previous mode state to the legacy layer. Suppressed while sending a message.

### `updateCurrentChatModelId(modelId)`

Called whenever the composer's selected model changes. Keeps the legacy model selector in sync. Accepts `null` to clear the selection.

### `updateActiveConversationSystemPrompt(prompt)`

Called when the user edits the system prompt inside the Svelte composer. Pushes the new value to the legacy layer so it can persist the change.

### `triggerEnterMode(mode, data)`

Called by the legacy layer to push a mode change into the composer. Also available as a Svelte-side call if one Svelte component needs to signal a mode change through the bridge (rare — prefer direct store updates for pure Svelte interactions). Suppressed while sending a message.

### `triggerExport(exportType)`

Called by the Svelte UI when the user selects an export format. The legacy layer handles the actual export operation.

### `triggerOpenChat(slug)`, `triggerNewChat()`, `triggerRenameChat(slug, name)`, `triggerDeleteChat(slug)`, `triggerLeaveRoom(slug)`

Navigation actions initiated from Svelte. The legacy layer handles routing and state changes. All are suppressed while sending a message, except `triggerRenameChat`.

### `triggerImproveMessage(message, systemPrompt)`

Async. Asks the legacy layer to improve the given message using AI. Returns a promise that resolves to the improved message string. Returns the original message unchanged if a send is in progress.

### `triggerSendToast(message, type)`

Fires a toast notification. Can be called from either the Svelte layer or the legacy layer — both sides can trigger toasts and the Svelte toast component handles rendering.

### `triggerOpenRoomControlPanel(slug)`, `triggerMarkRoomMessagesAsRead(slug)`

Room-specific triggers called from Svelte. The legacy layer handles the operations.

### `triggerPreviewAttachment(fileData)`, `triggerDownloadAttachment(fileData)`, `triggerDeleteAttachment(fileData)`

Called from Svelte attachment components to delegate file operations to the legacy layer.

### `bindAbortController(controller)`

Called by the legacy layer to register an `AbortController` for the current send operation. The bridge relays it to the active `SendMessageResponse` tracker so that `response.abort()` cancels the underlying request. This is used internally during `triggerSendMessage`.

### `passkey` (reactive state property)

```ts
public passkey = $state<string | null>(null);
```

A Svelte 5 `$state` field holding the user's decrypted passkey for the current session. It starts as `null` and is populated by the legacy layer once the user unlocks. Svelte components that need the passkey read this property reactively.

## `OldUiMessageHistory`

`OldUiMessageHistory` is a companion singleton that holds the read-state of the active conversation. Import the singleton:

```ts
import { oldUiMessageHistory } from '$lib/oldUi/OldUiMessageHistory.svelte.js';
```

### Reactive Properties

| Property | Type | Description |
|---|---|---|
| `conversationName` | `string` | Display name of the active conversation. |
| `conversationSlug` | `string` | URL slug of the active conversation. |
| `isInConversation` | `boolean` | `true` once a conversation has been loaded. |
| `systemPrompt` | `string` | The active conversation's system prompt. |
| `canAdministrate` | `boolean` | `true` if the user has the `admin` role in the conversation, or if the context type is `aiConv`. |
| `canWrite` | `boolean` | `true` if the user can send messages — i.e. they are an admin or an editor. `false` for archived conversations or shared conversations where the user only has viewer access. |

All properties are `$derived` values and update reactively when the underlying conversation state changes.

### `onLoadConversation(handler)`

Registers a handler that fires whenever the legacy layer loads a new conversation. Receives the full `OldUiConversation` object. Svelte components use this to initialize UI state (e.g. pre-populate the system prompt field, reset scroll position).

Returns an unsubscribe function.

### Mutation Methods

The legacy layer calls these to keep the history in sync as the conversation evolves:

- `loadConversation(type, conversation)` — replaces the entire active conversation and fires `onLoadConversation` handlers.
- `updateConversation(partial)` — merges a partial update into the active conversation (e.g. after a rename or a system-prompt change).
- `clearConversation()` — resets all state; called when navigating away.
- `addMessageToConversation(message)` — appends a new message.
- `updateMessageInConversation(message)` — replaces an existing message by `message_id`.
- `removeMessageFromConversation(messageId)` — removes a message by ID.
- `removeFileByUuid(fileUuid)` — strips an attachment from all messages.

### Lookup Methods

- `findMessageById(messageId)` — returns the matching `OldUiConversationMessage` or `null`.
- `findMessageByAttachmentUuid(fileUuid)` — returns the message that contains the given attachment UUID, or `null`.

### `canWrite` in Practice

Before enabling the composer's send button or the system-prompt editor, check `oldUiMessageHistory.canWrite`. This is `false` for conversations the user can only view (e.g. a shared room where they hold the `viewer` role). It is `true` for any conversation where the user is `admin` or `editor`, and also `true` for all personal AI conversations (`aiConv` context type, which implies full ownership).

## When Not to Use the Bridge

The bridge exists solely for interop with the legacy layer. Any communication between two Svelte components, between a component and a Svelte store, or between stores should use Svelte stores or Svelte context — not `oldUiBridge`.

If you find yourself calling `oldUiBridge.triggerSendToast` from one Svelte component to notify another Svelte component, introduce a dedicated store or a context value instead. Using the bridge for pure Svelte-to-Svelte communication adds unnecessary indirection and ties the new code to a construct that will be removed once the legacy layer is gone.
