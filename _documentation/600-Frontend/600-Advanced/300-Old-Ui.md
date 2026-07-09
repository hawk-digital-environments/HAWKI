# Old UI Integration

The HAWKI chat UI is being progressively rewritten from vanilla JS to Svelte 5. During this transition, both layers need to coexist: the new Svelte frontend must expose capabilities that legacy JS code can consume, and the two layers need typed communication channels for the parts of the UI they share.

The integration surface consists of three parts:

1. **Boot coordination** — the legacy layer must wait until the Svelte app has finished booting before it can safely access anything the new frontend provides.
2. **Window globals** — a set of functions and objects the new frontend exposes directly on `window` for use by legacy JS and inline Blade scripts.
3. **Typed event bridges** (`OldUiBridge`, `OldUiMessageHistory`) — the main communication channel for the chat UI specifically.

---

## Waiting for Boot

The Svelte app boots asynchronously. Legacy JS and inline Blade scripts that need to access new-frontend APIs must wait for the relevant boot milestone before proceeding. Two global functions handle this.

These functions are declared by the `EarlyFrontendBridge` Laravel component (`app/Services/Frontend/View/EarlyFrontendBridge.php`), which injects a small inline `<script>` into the page **before** the Svelte bundle loads. This guarantees the functions exist on `window` from the very first moment of page execution. The queued callbacks are then drained inside `app.ts` once the respective milestone is reached.

### `window.waitUntilBootstrap(callback)`

Calls `callback(bootstrapper)` as soon as the `Bootstrapper` instance is ready — i.e., the moment `app.ts` has registered all boot handlers but before any stage has run. Use this when legacy code needs to register additional boot handlers itself.

```js
window.waitUntilBootstrap(function(bootstrapper) {
    bootstrapper.onMainStage(async function() {
        // runs during the main boot stage alongside other handlers
    });
});
```

If called after the bootstrapper is already available, the callback fires immediately with a console warning.

### `window.waitUntilReady(callback)`

Calls `callback()` after the full boot sequence has completed — equivalent to waiting for all stages through `finalization` to resolve. Use this for any initialization that must happen after the frontend is fully operational.

```js
window.waitUntilReady(function() {
    // safe to use window.oldUiBridge, window.getConfig, etc.
});
```

If called after boot has already completed, the callback fires immediately with a console warning.

Both functions are used throughout the legacy UI JavaScript (`public/js/`) and in inline scripts within Blade view templates (`resources/views/`). The pattern is always the same: wrap any access to new-frontend APIs in one of these two guards.

---

## Window Globals

`app.ts` exposes a set of functions and objects directly on `window` so legacy code can access them without ES module imports. These are **legacy compatibility bridges only** — new Svelte code must never read from `window.*`; it should import directly from the module instead.

| Global | Type | Description |
|---|---|---|
| `window.hawkiBootstrap` | `Bootstrapper` | The bootstrapper instance. Available after `waitUntilBootstrap`. |
| `window.hawkiIsReady` | `boolean` | `true` once boot completes. Prefer `waitUntilReady` over polling this. |
| `window.oldUiBridge` | `OldUiBridge` | The primary typed event bus between the two layers. See [OldUiBridge](#olduibridge) below. |
| `window.oldUiMessageHistory` | `OldUiMessageHistory` | The reactive conversation state object. See [OldUiMessageHistory](#olduimessagehistory) below. |
| `window.getConfig()` | `function` | Returns the `hawki-core` config slice. Same as `getConfig()` from the data layer. |
| `window.getAuthenticatedConnection()` | `function` | Throws if the connection is not authenticated. Returns `InternalAuthenticatedConnection`. |
| `window.getConnectionWithUserInfo()` | `function` | Returns connection with user info for both authenticated and registering users. |
| `window.__` | `function` | The translation function. Same as `__()` from the translator. |
| `window.applyMigrations(runType)` | `function` | Runs frontend migrations for the given run type. |
| `window.userKeychain` | `KeychainStore` | The keychain store instance. Provides access to the user's encryption keys after passkey entry. |
| `window.hawkiDependencyLoader` | `function` | The lazy dependency loader. Same as `dependencyLoader()` from `dependencies.js`. |
| `window.getAiModels()` | `function` | Returns the current `aiModelStore.models` array. |
| `window.getAiModel(id)` | `function` | Returns the model matching `id`, or `null`. Accepts numeric ID or `model_id` string. |
| `window.getSystemModel(modelType)` | `function` | Returns the model assigned to a system role (e.g. `'default'`), or `null`. |
| `window.getSystemPrompt(promptType)` | `function` | Returns the `prompt` string for a well-known system prompt type, or `null`. |

> **Do not use these globals from new Svelte code.** Import the relevant module directly. The globals exist to keep the legacy JS layer functional during the transition.

---

## OldUiBridge

The primary typed event bus for the chat UI. Import the singleton — do not instantiate the class:

```ts
import { oldUiBridge } from '$lib/oldUi/OldUiBridge.svelte.js';
```

### The Rule

The bridge is the **only** sanctioned way for new Svelte code to talk to legacy code and vice versa within the component ecosystem.

- Do not call legacy functions directly from Svelte components.
- Do not reach into Svelte stores or Svelte context from legacy JS.
- Do not bypass the bridge by importing legacy modules into Svelte or Svelte stores into legacy modules.

### Events: Legacy → Svelte

Events triggered by the legacy layer. Svelte components register handlers via the `on*` methods. Each returns an unsubscribe function — call it during `onDestroy` or equivalent cleanup.

#### `onClearActiveConversation(handler)`

Triggered when the currently active conversation is cleared (e.g. the user navigates away or the conversation is deleted). The Svelte composer should reset all its state.

Payload: `void`.

#### `onLoadSystemPrompt(handler)`

Triggered when the legacy layer wants to push a new system prompt into the Svelte composer. The composer should replace its current system prompt field.

Payload: `string` — the system prompt text.

#### `onLoadInitialModel(handler)`

Triggered once after a conversation loads, passing the AI model that should be selected by default. Treat it as an initialization event, not a user-driven selection.

Payload: `AiModel`.

#### `onEnterMode(handler)`

Triggered when the legacy layer requests that the composer enter a named mode (`edit`, `thread`, `regen`, …). Suppressed while a message send is in progress.

```ts
oldUiBridge.onEnterMode((mode, data) => { /* ... */ });
```

Payload: `{ mode: ComposerModeWithIs, data: unknown }`.

#### `onExitThread(handler)`

Triggered when the legacy layer requests that thread mode be exited. Suppressed while sending.

Payload: `void`.

#### `onSendToast(handler)`

Triggered by any code — legacy or Svelte — that wants to display a toast notification.

Payload: `{ message: string, type: 'success' | 'error' | 'info' }`.

#### `onExitMode(handler)`

Triggered when the current non-default mode is exited. The handler receives the state of the mode that was active.

Payload: `ComposerModeWithIs`.

#### `onActiveConversationSystemPromptUpdate(handler)`

Triggered when Svelte calls `updateActiveConversationSystemPrompt`. The legacy side receives system-prompt changes initiated from within the composer.

Payload: `string`.

#### `onCurrentChatModelIdUpdate(handler)`

Triggered when Svelte calls `updateCurrentChatModelId`. The legacy model selector listens here.

Payload: `string | null`.

#### `onContextReady(handler)`

Triggered once when the Svelte composer mounts and calls `triggerContextReady`. If the composer is already ready when `onContextReady` is called, the handler fires immediately.

Payload: `void`.

#### `onSendMessage(contextType, handler)`

Triggered when the Svelte composer calls `triggerSendMessage`. The handler only fires when the payload's `contextType` matches the registered value, letting different legacy handlers cover room vs. AI conversations.

The async handler receives `OldUiSendMessagePayload` — message text, model, system prompt, tools, attachments, parameters, and control callbacks (`setResponse`, `setResponseFailed`, `waitForResponse`).

#### `onOpenChat`, `onNewChat`, `onRenameChat`, `onDeleteChat`, `onLeaveRoom`

Navigation and conversation management events triggered by Svelte UI actions. Each is suppressed while sending.

- `onOpenChat` — payload: `string` (slug)
- `onNewChat` — payload: `void`
- `onRenameChat` — payload: `{ chatSlug: string, newName: string }`
- `onDeleteChat` — payload: `string` (slug)
- `onLeaveRoom` — payload: `string` (slug)

#### `onExportTrigger(handler)`

Triggered when Svelte calls `triggerExport`. The legacy layer handles the actual export.

Payload: `OldUiExportType` — `'print' | 'pdf' | 'word' | 'json' | 'csv'`.

#### `onOpenRoomControlPanel(handler)`, `onMarkRoomMessagesAsRead(handler)`

Room-specific events. Payload: `string` (room slug) for both.

#### `onImproveMessage(handler)`

Async event triggered when the composer requests AI-assisted message improvement. The handler must return the improved message text as a string.

Payload in: `{ message: string, systemPrompt: string }`. Return value: `string`.

#### `onPreviewAttachment`, `onDownloadAttachment`, `onDeleteAttachment`

Attachment lifecycle events. The Svelte UI triggers; the legacy layer handles file operations.

Payload: `OldUiFileData` — `{ uuid, name, mime, type, url, category }`.

---

### Calls: Svelte → Legacy

Methods called from Svelte components or stores. The legacy layer registers the corresponding `on*` handlers.

#### `triggerSendMessage(payload)`

Sends the composed message through the legacy pipeline. Sets an internal `isSendingMessage` flag for its duration — while set, several other triggers are suppressed to prevent state corruption mid-send.

#### `triggerContextReady()`

Called by the Svelte composer once on mount. Sets the internal `isContextReady` flag and fires all registered `onContextReady` handlers. Any handler registered after this fires immediately.

#### `triggerExitMode(oldState)`

Called when the composer exits a non-default mode. Suppressed while sending.

#### `updateCurrentChatModelId(modelId)`

Called whenever the composer's selected model changes. Accepts `null` to clear.

#### `updateActiveConversationSystemPrompt(prompt)`

Called when the user edits the system prompt. Pushes the new value to the legacy layer.

#### `triggerEnterMode(mode, data)`

Pushes a mode change into the composer. Suppressed while sending.

#### `triggerExport(exportType)`

Called by the Svelte UI when the user selects an export format.

#### `triggerOpenChat(slug)`, `triggerNewChat()`, `triggerRenameChat(slug, name)`, `triggerDeleteChat(slug)`, `triggerLeaveRoom(slug)`

Navigation triggers. All suppressed while sending, except `triggerRenameChat`.

#### `triggerImproveMessage(message, systemPrompt)`

Async. Returns the improved message string, or the original when a send is in progress.

#### `triggerSendToast(message, type)`

Fires a toast notification from either layer.

#### `triggerOpenRoomControlPanel(slug)`, `triggerMarkRoomMessagesAsRead(slug)`

Room-specific triggers.

#### `triggerPreviewAttachment(fileData)`, `triggerDownloadAttachment(fileData)`, `triggerDeleteAttachment(fileData)`

Delegate file operations to the legacy layer.

#### `bindAbortController(controller)`

Called by the legacy layer to register an `AbortController` for the current send. The bridge relays it to the active response tracker so `response.abort()` cancels the underlying request.

#### `passkey` (reactive state)

```ts
public passkey = $state<string | null>(null);
```

Holds the user's decrypted passkey for the current session. Starts as `null`, populated by the legacy layer once the user unlocks. Svelte components that need the passkey read this reactively.

---

## OldUiMessageHistory

A companion singleton that holds the read-state of the active conversation. Import it:

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
| `canAdministrate` | `boolean` | `true` if the user has the `admin` role, or the context type is `aiConv`. |
| `canWrite` | `boolean` | `true` if the user can send messages (admin or editor). `false` for viewer-only or archived conversations. |

All properties are `$derived` and update reactively.

### `onLoadConversation(handler)`

Registers a handler that fires whenever the legacy layer loads a new conversation. Receives the full `OldUiConversation` object. Returns an unsubscribe function.

### Mutation Methods (called by the legacy layer)

- `loadConversation(type, conversation)` — replaces the active conversation and fires handlers.
- `updateConversation(partial)` — merges a partial update (rename, system-prompt change, etc.).
- `clearConversation()` — resets all state; called when navigating away.
- `addMessageToConversation(message)` — appends a message.
- `updateMessageInConversation(message)` — replaces a message by `message_id`.
- `removeMessageFromConversation(messageId)` — removes a message by ID.
- `removeFileByUuid(fileUuid)` — strips an attachment from all messages.

### Lookup Methods

- `findMessageById(messageId)` — returns `OldUiConversationMessage` or `null`.
- `findMessageByAttachmentUuid(fileUuid)` — returns the message containing the attachment UUID, or `null`.

### `canWrite` in Practice

Check `oldUiMessageHistory.canWrite` before enabling the composer's send button or the system-prompt editor. It is `false` for conversations the user can only view, and `true` for any conversation where the user is `admin`, `editor`, or the owner of a personal AI conversation (`aiConv` context type).

---

## When Not to Use the Bridge

The bridge exists solely for interop with the legacy layer. Any communication between two Svelte components, between a component and a Svelte store, or between stores should use Svelte stores or context — not `oldUiBridge`.

If you find yourself calling `oldUiBridge.triggerSendToast` from one Svelte component to notify another Svelte component, introduce a dedicated store or context value instead. Using the bridge for pure Svelte-to-Svelte communication adds unnecessary indirection and ties the new code to a construct that will be removed once the legacy layer is gone.
