# Composer

The composer is the chat input area — the region where the user types a message, picks a model, attaches files, enables tools, and finally sends. All of that state lives in a single object called `ComposerContext`.

Source directory: `resources/js/components/chat/composer/`

---

## Architecture Overview

`ComposerContext` is the single state container that every composer Svelte component reads from and writes to. Rather than one monolithic class, state is divided into focused _aspect_ classes, each owning exactly one concern. Two additional derived-view aspects (no mutable state of their own) provide computed properties consumed by the UI. A pluggable _mode_ system layers temporary overlays on top of that state, and a dedicated _send pipeline_ handles the HTTP/transport lifecycle.

### Aspects

| Property | Class | Owns |
|---|---|---|
| `context.model` | `ModelAspect` | selected AI model |
| `context.modelParameters` | `ModelParameterAspect` | temperature / top_p (resets on model switch unless user-modified) |
| `context.tools` | `ToolAspect` | user-enabled tools for the request |
| `context.attachments` | `AttachmentAspect` | staged file attachments |
| `context.modelUsage` | `ModelUsageAspect` | derived: is the current model compatible with active tools/files? |
| `context.guard` | `GuardAspect` | derived: canSend, canChangeMode, disablesFeature() |
| `context.mode` | `ModeAspect` | active mode + transition lifecycle |

`ModelUsageAspect` and `GuardAspect` hold no mutable state — they are pure derived views and are never checkpointed.

---

## Getting the Context

### `useComposerContext()`

Call in any child composer component to retrieve the nearest `ComposerContext` from the Svelte context tree. Throws a descriptive error if no context is found, so missing wiring fails loudly during development.

```ts
import { useComposerContext } from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';

const context = useComposerContext();
```

### `createComposerContext(type, toastContext)`

Call once, in the composer root component. Constructs all aspects, wires them together, subscribes to `OldUiBridge` events (model load, system prompt changes, mode enter/exit, write-access updates), and registers the context in the Svelte tree with `setContext`. Cleanup is handled automatically via `onDestroy`.

```ts
import { createComposerContext } from '$lib/components/chat/composer/contexts/ComposerContext.svelte.js';

const context = createComposerContext('aiConv', toastContext);
```

`type` is either `'aiConv'` (a dedicated AI conversation view) or `'room'` (a room chat where AI elements only appear when the message contains an `@handle` or regen mode is active).

This follows the standard HAWKI context pattern described in Basics → Svelte Components.

---

## Aspects

### ModelAspect

`context.model`

Tracks the currently selected AI model and exposes derived capability flags the UI uses to show/hide controls.

| Member | Type | Notes |
|---|---|---|
| `current` | `AiModel` | The active model object. |
| `allowsFileUpload` | `boolean` (derived) | Shorthand for `model.settings.file_upload`. Use to show/hide the attachment button. |
| `allowsToolCalling` | `boolean` (derived) | Shorthand for `model.settings.tool_calling`. Use to show/hide the tool menu. |
| `hasVision` | `boolean` (derived) | `true` when the model accepts file uploads **and** lists `'image'` as a supported input type. |

**`set(model)`** — selects a new model. Accepts an `AiModel` object, a `model_id` string, a numeric model `id`, or `null` (falls back to the "default" system model). On switch, sampling parameters reset to the new model's defaults unless the user had already modified them (`modelParameters.isModified`).

### ModelParameterAspect

`context.modelParameters`

Manages sampling parameters (`temperature`, `top_p`) for the next request.

| Member | Type | Notes |
|---|---|---|
| `list` | `Record<string, unknown>` | Current parameter values. |
| `defaults` | `Record<string, unknown>` (derived) | Global fallbacks (`temperature=0.7`, `top_p=0.9`) merged with model-specific defaults. |
| `modelDefaults` | `Record<string, unknown>` (derived) | Parameters declared by the current model definition from the server. |
| `isModified` | `boolean` (derived) | `true` when `list` differs from `modelDefaults` in any key or value. Checked by `ModelAspect.set()` before resetting on a model switch. |

**`get(key)`** — returns the current value for a parameter, falling back to model defaults then global defaults.

**`set(key, value)`** — sets a single parameter.

**`reset()`** — resets all parameters to `defaults` (model-specific values merged over global fallbacks).

**`intersects(other)`** — returns `true` when every key/value pair in `other` matches the current values. Useful for checking whether a preset is already active.

### AttachmentAspect

`context.attachments`

Manages files staged for the next message.

| Member | Type | Notes |
|---|---|---|
| `list` | `File[]` (derived) | All currently staged files. |
| `hasAny` | `boolean` (derived) | `true` when at least one file is staged. |
| `hasImages` | `boolean` (derived) | `true` when at least one staged file has an `image/*` MIME type. |
| `allowedMimeTypes` | `string[]` (derived) | MIME types permitted by server config. Use as the `accept` attribute on file inputs. |
| `allowedExtensions` | `string[]` (derived) | File extensions permitted by server config (e.g. `['pdf', 'png']`). |

**`add(file | FileList)`** — appends one file or all files from a `FileList`. Every supported file is added; unsupported or oversized files are skipped. Returns `true` when all files were accepted, or an array of `FileAttachmentIssue` objects for the skipped ones (the accepted files are still added).

```ts
const result = context.attachments.add(event.target.files);
if (result !== true) {
    result.forEach(issue => {
        if (issue.type === 'file_too_large') { /* ... */ }
        if (issue.type === 'unsupported_file_type') { /* ... */ }
    });
}
```

**`remove(file)`** — removes a specific file by reference.

**`clear()`** — removes all staged files and clears all assigned UUIDs.

**`assignUuid(file, uuid)`** — records the server-assigned UUID for a file after it has been uploaded. Called by the transport as each upload completes, not by components.

**`getAssignedUuid(file)`** — returns the assigned UUID, or `null` if the file has not been uploaded yet.

### ToolAspect

`context.tools`

Manages which tools the user has enabled for the next request.

| Member | Type | Notes |
|---|---|---|
| `active` | `AiTool[]` | Tools the user has explicitly enabled. De-duplicated by tool name. |
| `available` | `AiTool[] \| null` (derived) | Full set of tools the current model supports, or `null` if tool calling is disabled. |
| `availableCapabilities` | `AiToolCapability[] \| null` (derived) | Capability definitions for the current model, or `null` if tool calling is disabled. |

**`add(tool)`** — enables a tool by name or object. No-op if already active.

**`remove(tool)`** — disables a tool by name or object. No-op if not active.

**`clear()`** — disables all active tools.

**`canUse(tool)`** — returns `true` when the current model supports tool calling and the given tool is in the model's available tool list.

### ModelUsageAspect

`context.modelUsage`

Derived-only view — no mutable state, never checkpointed. Computes whether the selected model is compatible with the current tools and attachments, and why it isn't if not.

| Member | Type | Notes |
|---|---|---|
| `isValid` | `boolean` (derived) | `true` when the current model appears in `allUsable`. |
| `issues` | `ModelUsageIssue[]` (derived) | Reasons the current model cannot be used (empty = compatible). Issue types: `'no_tool_calling'`, `'no_file_upload'`, `'no_vision'`, `'missing_tools'`. |
| `allUsable` | `AiModel[]` (derived) | All models compatible with the current tools and attachments. Use to populate a "suggested models" list when `isValid` is `false`. |

Issues are suppressed (returns an empty array) when the mode disables model selection (`guard.disablesFeature('models')`) or when AI UI elements are not shown (`guard.showsAiUiElements === false`), since in those cases the user didn't choose the model and cannot change it.

### GuardAspect

`context.guard`

Derived-only view — no mutable state, never checkpointed. Centralises send-permission and mode-change-permission logic so individual components don't replicate those checks.

**`canSend`** (derived boolean) — composite gate that returns `true` only when all of the following hold:

1. `context.forcedActive` is `false`
2. `context.hasWriteAccess` is `true`
3. No send is currently active (`sendStatus?.active` is falsy)
4. `context.messageWithoutHandles` is non-empty after trimming
5. `context.modelUsage.issues` is empty
6. The active mode's own `canSend()` returns `true`

**`showsAiUiElements`** (derived boolean) — controls whether AI-related UI (model picker, tool menu, etc.) should be visible. Always `true` in `'aiConv'` type; in `'room'` type, `true` only when regen mode is active or `context.containsAiHandle` is `true`.

**`canChangeMode`** (derived boolean) — `false` while a send is active (`sendStatus?.active`), while `forcedActive` is set, or when the conversation has no write access.

**`disablesFeature(feature, disableWhileActive?)`** — two-layer check:

1. When `disableWhileActive` is `true` (the default) and the status is `sending` or `forcedActive` is set, returns `true` immediately.
2. Delegates to `context.mode.instance.disablesUiFeature(feature)` to let the active mode lock specific controls.

Pass `disableWhileActive: false` for features that should stay interactive during a send (e.g. allowing the user to see the model picker while a response streams).

Disableable features: `'models'`, `'settings'`, `'attachments'`, `'tools'`, `'input'`, `'suggestions'`.

### ModeAspect

`context.mode`

Manages the active composer mode and its enter/exit lifecycle.

| Member | Type | Notes |
|---|---|---|
| `is` | `string` (derived) | The key of the currently active mode: `'default'`, `'edit'`, `'thread'`, or `'regen'`. |
| `state` | `ComposerModeWithIs` | Persistent data returned by the mode's `enter()` call, tagged with `is`. |
| `instance` | `ChatModeInterface` | The active mode strategy object. |
| `exitAfterSend` | `boolean` (derived) | Whether the active mode should exit automatically after a message is sent. |
| `isDefault` | `boolean` (derived) | Shorthand for `is === 'default'`. |
| `isEdit` | `boolean` (derived) | Shorthand for `is === 'edit'`. |
| `isThread` | `boolean` (derived) | Shorthand for `is === 'thread'`. |
| `isRegen` | `boolean` (derived) | Shorthand for `is === 'regen'`. |

**`getState<T>(mode)`** — returns `state` narrowed to the type for `mode`. Throws if the current mode does not match — always guard with `is` first:

```ts
if (context.mode.is === 'edit') {
    const editState = context.mode.getState('edit');
    // editState.messageId, editState.originalMessage, editState.originalAttachments
}
```

**`enter(mode, data)`** — transitions to `mode`. Checks `guard.canChangeMode`, validates `canEnter()` on the new mode instance (showing an error toast for string returns), saves a checkpoint, then calls `enter()` on the mode instance to mutate context.

**`exit()`** — exits the current mode by restoring the checkpoint saved during `enter()`. This resets the entire context (message, model, tools, attachments, etc.) to its state before the mode was entered.

---

## Modes

The composer always has exactly one active mode. The default mode is `ChatDefaultMode` and is active from construction without any checkpoint being saved.

### Lifecycle

```
enter(mode, data)
  ├── guard.canChangeMode?          → show toast if blocked
  ├── mode.canEnter(context, data)  → silent abort (false) or error toast (string)
  ├── checkpointer.createCheckpoint(mode.allowsNestedModes())
  └── mode.enter(context, data)     → mode mutates context; returns state

exit()
  ├── guard.canChangeMode?          → silent abort if blocked
  └── checkpointer.restoreCheckpoint()
        → all aspects + context reset to pre-enter state
        → mode.exit() called on the outgoing instance
```

### Mode Table

| Mode key | Class | Purpose |
|---|---|---|
| `default` | `ChatDefaultMode` | Normal compose; stays active after send (`exitAfterSend = false`) |
| `edit` | `ChatEditMode` | Edit a past user message; locks model, settings, and tools UI; blocks send until message or attachments change |
| `thread` | `ChatInThreadMode` | Compose inside a thread; allows nested edit/regen modes; stays active after send |
| `regen` | `ChatRegenMode` | Regenerate an assistant reply; pre-fills model and params from the original message; locks attachments, input, and suggestions |

`ChatInThreadMode.allowsNestedModes()` returns `true`, which is what permits edit and regen to be entered while a thread is active — the thread checkpoint is preserved on the stack beneath the nested mode checkpoint.

### Mode Details

**`ChatDefaultMode`** — carries no state (`{}`). Never exits after send so the user can continue typing in the same session.

**`ChatEditMode`** — takes an `OldUiConversationMessage` as data. Pre-fills the composer with the original message text and reconstructs any attachments as `RemoteFile` references. Stored state: `{ messageId, originalMessage, originalAttachments }`. `canSend` returns `false` until the message text or attachment list has changed from the original. Disables `'models'`, `'settings'`, `'tools'` UI features.

**`ChatInThreadMode`** — takes a `threadId` string as data. Resets the composer on enter and focuses the input. Stored state: `{ threadId }`. Because `allowsNestedModes()` is `true`, the user can enter edit or regen mode for messages within the thread without losing the thread context.

**`ChatRegenMode`** — takes an `OldUiConversationMessage` (must be an assistant message) as data. On enter, pre-fills the model and sampling parameters from `data.model` and `data.metadata.params`, and restores tools from `data.metadata.tools`. Sets a placeholder message so `canSend` in `GuardAspect` passes the non-empty check. Disables `'attachments'`, `'input'`, `'suggestions'`. Exits after send.

### Writing a New Mode

Implement `ChatModeInterface` or extend `AbstractMode` (preferred — provides safe no-op defaults for all optional methods):

```ts
import { AbstractMode } from '.../modes/contracts/AbstractMode.js';
import type { ComposerContext } from '.../ComposerContext.svelte.js';

interface MyModeData { /* passed to enter() */ }
interface MyModeState { /* returned by enter(), persisted as context.mode.state */ }

export class ChatMyMode extends AbstractMode<MyModeData, MyModeState> {
    public enter(context: ComposerContext, data: MyModeData): MyModeState {
        // Mutate context for this mode's purpose
        context.reset();
        context.message = data.prefill ?? '';
        context.focusInput();
        return { /* state to keep */ };
    }

    // Override only what differs from AbstractMode defaults:
    // canEnter, canSend, disablesUiFeature, exitAfterSend, allowsNestedModes, exit
}
```

Then register it in the factory switch inside `createComposerContext()` in `ComposerContext.svelte.ts`:

```ts
const mode = new ModeAspect(
    checkpointer,
    toastContext,
    (mode) => {
        switch (mode) {
            case 'edit':   return new ChatEditMode();
            case 'thread': return new ChatInThreadMode();
            case 'regen':  return new ChatRegenMode(aiModelStore, aiToolStore, toastContext);
            case 'my':     return new ChatMyMode();  // ← add here
            default: throw new Error(`Unsupported mode ${mode}`);
        }
    },
    ...
);
```

Also add the new key to `ComposerModeRegistry` in `ModeAspect.svelte.ts` so `enter()` and `getState()` are properly typed.

---

## Checkpointing

Every stateful aspect implements `CheckpointingInterface`:

```ts
interface CheckpointingInterface<T = unknown> {
    createCheckpoint(): T;
    restoreCheckpoint(checkpoint: T): void;
}
```

`ContextCheckpointer` coordinates snapshotting all aspects simultaneously. `ComposerContext` itself also registers handlers with the checkpointer (for `message`, `systemPrompt`, and `sendStatus`). When `ModeAspect.enter()` fires, `ContextCheckpointer.createCheckpoint()` fans out to every registered handler, collecting a full-state snapshot. When `ModeAspect.exit()` fires, `restoreCheckpoint()` broadcasts the saved slices back to all handlers in order.

The checkpointer maintains a stack. The `allowsNested` flag on `createCheckpoint(allowsNested?)` controls whether a second call is permitted while a checkpoint is already on the stack. `ChatInThreadMode` passes `true`, which allows edit or regen to stack a second checkpoint on top of the thread's checkpoint without discarding it. All other modes pass `false` (the default), so subsequent `enter()` calls while a checkpoint exists are ignored.

Components and modes never need to know what each aspect saves — the coordinator handles everything automatically.

---

## Key `ComposerContext` Properties and Methods

### State Properties

| Property | Type | Notes |
|---|---|---|
| `type` | `'aiConv' \| 'room'` | Readonly. Set at construction. Affects `guard.showsAiUiElements`. |
| `message` | `string` ($state) | The text currently in the input. Bind directly or set imperatively. |
| `messageWithoutHandles` | `string` (derived) | `message` with all `@handle` tokens stripped and whitespace normalised. Used by `guard.canSend` to detect actual content. |
| `handlesInMessage` | `string[]` (derived) | All `@handle` tokens found in `message` (e.g. `['@hawki']`). |
| `containsAiHandle` | `boolean` (derived) | `true` when `handlesInMessage` is non-empty. |
| `sendStatus` | `SendMessageStatus \| null` (derived) | The active send operation, or `null` when idle. Cleared automatically once the response body resolves. |
| `systemPrompt` | `string` (getter/setter) | The system prompt for this session. Setting it propagates to `OldUiBridge` (suppressed when the bridge itself is loading a prompt). |
| `hasWriteAccess` | `boolean` ($state) | `false` for read-only conversations (archived, shared). Updated via `OldUiMessageHistory`. |
| `forcedActive` | `boolean` ($state) | When `true`, disables the composer UI. Set externally by processes that need to occupy the composer (e.g. a background file upload). |

### Methods

**`send()`** — starts a send operation. Returns `null` without any side effects when `guard.canSend` is `false`. On success, creates a `SendMessageStatus`, stores it on `sendStatus`, and clears `sendStatus` automatically once `response.body` resolves. Returns the `SendMessageStatus` to the caller.

**`clear()`** — called after a successful send. Clears `message` (preserving any `@handle` tokens so the user can keep chatting with the same AI without re-tagging) and clears `attachments`. Does **not** reset model, parameters, tools, or mode.

**`reset(withCheckpoint?)`** — full reset back to initial state: empties `message`, clears `attachments`, resets parameters, clears tools, restores `systemPrompt` to the initial value, and nulls `sendStatus`. When `withCheckpoint` is `true`, also calls `checkpointer.restoreCheckpoint()` which exits any active mode.

**`addHandleToMessage(handle)`** — prepends `handle` to `message` if it is not already present, then calls `focusInput()`.

**`focusInput()`** — imperatively requests focus on the textarea. Called by modes after pre-filling the message so the cursor lands in the input.

**`onFocusInput(handler)`** — registers a handler that fires whenever `focusInput()` is called. Returns an unsubscribe function. Typically called by the textarea component:

```ts
onMount(() => {
    return context.onFocusInput(() => textarea.focus());
});
```

---

## Send Pipeline

### Flow

```
context.send()
  └── guard.canSend?  → return null if false
      └── MessageSender.send(context)
            ├── Creates SendMessageStatus (+ response Promise)
            ├── Creates SendMessageResponse (write surface)
            └── transport.sendMessage(opt)
                  ├── opt.waitForResponse(handler)     ← streaming
                  │     handler receives SendMessageResponse
                  │     handler calls response.triggerBodyChunk(chunk) for each chunk
                  │     handler calls response.triggerReceived() when done
                  │       or response.triggerError(msg) on failure
                  ├── opt.setResponse(body)             ← non-streaming
                  └── opt.setResponseFailed(error)      ← failed send

UI observes:
  context.sendStatus          — the SendMessageStatus
  context.sendStatus.response — Promise<ResponseReader>
  responseReader.onBodyChunk  — subscribe to streaming chunks
  responseReader.body         — Promise<ResponseBody> resolves when complete
```

### `SendMessageStatus` States

| State | Meaning |
|---|---|
| `sending` | Request is being transmitted. |
| `responding` | Transport acknowledged; waiting for the full response body (may be streaming). |
| `received` | Complete response has arrived. |
| `failed` | A send or file error occurred. |

Boolean shorthands:

| Property | True when |
|---|---|
| `active` | `sending` or `responding` — use to disable the send button |
| `done` | `received` or `failed` |
| `sending` | Transmitting only |
| `responding` | Receiving only |
| `failed` | Error occurred |
| `received` | Success, body complete |

The status also tracks per-file upload progress (`getFileProgress(file)`), per-file errors (`getFileIssue(file)`), and non-file send errors (`sendIssues`).

### `ResponseReader`

The read-only subscriber view of `SendMessageResponse`. The UI receives this through `sendStatus.response` (a `Promise<ResponseReader>`):

```ts
const status = context.send();
if (!status) return;

const reader = await status.response;

// Subscribe to streaming chunks
reader.onBodyChunk((chunk) => appendToDisplay(chunk));

// Or wait for the full body
const body = await reader.body;

// Abort mid-stream if the transport supports it
if (reader.canAbort) reader.abort();
```

`ResponseReader` exposes: `onBodyChunk`, `onReceived`, `onError`, `onDone`, `abort`, `body`, `received`, `aborted`, `done`, `canAbort`, `bodyIsStream`.

---

## Implementing a New Transport

To replace or add a transport, implement `MessageSenderTransportInterface`:

```ts
interface MessageSenderTransportInterface {
    sendMessage(opt: MessageSenderTransportOptions): Promise<void>;
}
```

`opt` contains:

| Field | Type | Notes |
|---|---|---|
| `context` | `ComposerContext` | Full context snapshot for the send. |
| `status` | `SendMessageStatus` | Use to report file progress (`setFileProgress`), file UUIDs (`setFileUuid`), and file/send errors (`addFileIssue`, `addSendIssue`). |
| `setResponse(body)` | callback | Call once for a non-streaming response. |
| `setResponseFailed(error)` | callback | Call if the send itself fails before a response is available. |
| `waitForResponse(handler)` | callback | Call for streaming responses. The handler receives the `SendMessageResponse` write surface. Push chunks with `triggerBodyChunk()`, finalize with `triggerReceived()` or `triggerError()`. Only one call per send is allowed. |

A minimal streaming transport skeleton:

```ts
export class MyTransport implements MessageSenderTransportInterface {
    async sendMessage(opt: MessageSenderTransportOptions): Promise<void> {
        const { context, status, waitForResponse } = opt;

        // Upload files first if needed
        for (const file of context.attachments.list) {
            const uuid = await this.uploadFile(file, (progress) => {
                status.setFileProgress(file, progress);
            });
            status.setFileUuid(file, uuid);
            context.attachments.assignUuid(file, uuid);
        }

        // Open the streaming connection and hand the write surface to MessageSender
        waitForResponse(async (response) => {
            const stream = await this.openStream(context);
            for await (const chunk of stream) {
                response.triggerBodyChunk(chunk);
            }
            response.triggerReceived();
        });
    }
}
```

Then pass your transport to `MessageSender` inside `createComposerContext()`:

```ts
const sender = new MessageSender(new MyTransport(/* deps */));
```

The only current implementation is `OldUiBridgeTransport` (`contexts/sending/transport/OldUiBridgeTransport.ts`), which forwards requests to the legacy UI layer via `oldUiBridge.triggerSendMessage()`. Use it as a reference implementation.
