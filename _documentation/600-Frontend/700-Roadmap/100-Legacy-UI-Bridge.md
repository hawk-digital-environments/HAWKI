# Legacy UI Bridge

The HAWKI chat UI is being progressively rewritten from vanilla JS to Svelte 5. During this transition, both layers need to coexist: the new Svelte frontend must expose capabilities that legacy JS code can consume, and the two layers need typed communication channels for the parts of the UI they share.

The integration lives under `resources/js/legacy/` and consists of three parts:

1. **Boot coordination** — the legacy layer must wait until the Svelte app has finished booting before it can safely access anything the new frontend provides.
2. **Window globals** — a set of functions and objects the new frontend exposes directly on `window` for use by legacy JS and inline Blade scripts (published by `provideLegacyGlobals()` in `legacy/legacy.ts`).
3. **Typed event bridges** (`OldUiBridge`, `OldUiMessageHistory`) — the main communication channel for the chat UI specifically.

:::danger[Transitional — being phased out]
Everything in `legacy/` is `@deprecated` on purpose. It exists only for as long as the old Blade + vanilla-JS UI ships alongside the new Svelte app, and is meant to be deleted, not extended. New Svelte code must never read from `window.*` — import the real modules directly and use the `useApp()`/`useConfig()`/`useStore()` hooks instead. See [Technical Debt](../900-Technical-Debt.md).
:::

---

## Waiting for Boot

The Svelte app boots asynchronously. Legacy JS and inline Blade scripts that need to access new-frontend APIs must wait for the relevant boot milestone before proceeding. Two global functions handle this.

These functions are declared by the `EarlyFrontendBridge` Blade component (`app/Services/Frontend/View/EarlyFrontendBridge.php`), which injects a small inline `<script>` into the page **before** the Svelte bundle loads. This guarantees the functions exist on `window` from the very first moment of page execution. The queued callbacks are then drained inside `app.ts` once the respective milestone is reached.

### `window.waitUntilBootstrap(callback)`

Calls `callback(bootstrapper)` as soon as the `Bootstrapper` instance is ready — i.e., the moment `app.ts` has assembled the `HawkiApp` but before `bootstrapper.run()` has started. Use this when legacy code needs to register additional boot handlers itself.

```js
window.waitUntilBootstrap(function(bootstrapper) {
    bootstrapper.onMainStage(async function() {
        // runs during the main boot stage alongside other handlers
    });
});
```

If called after the bootstrapper is already available, the callback fires immediately with a console warning.

:::warning[No `window.hawkiBootstrap`]
The bootstrapper instance is **not** exposed as `window.hawkiBootstrap`. Use `waitUntilBootstrap()` to receive it — that is the only supported legacy accessor.
:::

### `window.waitUntilReady(callback)`

Calls `callback()` after the full boot sequence has completed — equivalent to waiting for all stages through `finalization` to resolve. Use this for any initialization that must happen after the frontend is fully operational.

```js
window.waitUntilReady(function() {
    // safe to use window.oldUiBridge, window.getConfig, etc.
});
```

If called after boot has already completed, the callback fires immediately with a console warning.

---

## Window Globals

`provideLegacyGlobals()` (called at the very top of `app.ts`, before the app is created) copies a hand-picked set of kernel functions, bridges, and stores onto `window` so legacy code can access them without ES module imports. Every app-dependent global is a closure or getter that resolves `getHawkiApp()` lazily, so it is safe to install them before the app exists.

| Global | Type | Description |
|---|---|---|
| `window.hawkiIsReady` | `boolean` | `true` once boot completes. Prefer `waitUntilReady` over polling this. |
| `window.oldUiBridge` | `OldUiBridge` | The primary typed event bus between the two layers. See [OldUiBridge](#olduibridge) below. |
| `window.oldUiMessageHistory` | `OldUiMessageHistory` | The reactive conversation state object. See [OldUiMessageHistory](#olduimessagehistory) below. |
| `window.getConfig()` | `function` | Returns the `hawki-core` config slice. Same as `useConfig()` / `app.config.get()`. |
| `window.getAuthenticatedConnection()` | `function` | Throws if the connection is not authenticated. Returns the authenticated connection. |
| `window.getConnectionWithUserInfo()` | `function` | Returns connection with user info for both authenticated and registering users. |
| `window.getConnection()` | `function` | Returns the full `Connection` union. |
| `window.__` | `function` | The translation function. Same as `useTranslator().__`. |
| `window.applyMigrations(runType)` | `function` | Runs frontend migrations for the given run type. (Deprecated — see [Reference → Utilities](../600-Reference/100-Utilities.md#migration-helpers-deprecated--libkernelmigrationshelpersts).) |
| `window.userKeychain` | `KeychainStore` | The keychain store instance (a getter, always resolves to the live store). Provides access to the user's encryption keys after passkey entry. |
| `window.hawkiDependencyLoader` | `function` | The lazy dependency loader. See [App Startup → Lazy Dependencies](../300-Architecture/110-App-Startup.md#lazy-dependencies). |
| `window.buildStorageFileUrl(id)` | `function` | Builds the proxied URL for a stored file. (Deprecated — use `UriBuilder.storageFileUri`.) |
| `window.getFileIconSvg(ext)` | `function` | Returns an inline SVG file-type icon for an extension. |
| `window.getAiModels()` | `function` | Returns the current `ai-models` store's `models` array. |
| `window.getAiModel(id)` | `function` | Returns the model matching `id`, or `null`. Accepts numeric ID or `model_id` string. |
| `window.getSystemModel(modelType)` | `function` | Returns the model assigned to a system role (e.g. `'default'`), or `null`. |
| `window.getSystemPrompt(promptType)` | `function` | Returns the `prompt` string for a well-known system prompt type, or `null`. |

> **Do not use these globals from new Svelte code.** Import the relevant module or use the matching hook directly. The globals exist to keep the legacy JS layer functional during the transition.

---

## OldUiBridge

The primary typed event bus for the chat UI. Import the singleton — do not instantiate the class:

```ts
import { oldUiBridge } from '$lib/legacy/OldUiBridge.svelte.js';
```

### The Rule

The bridge is the **only** sanctioned way for new Svelte code to talk to legacy code and vice versa within the component ecosystem.

- Do not call legacy functions directly from Svelte components.
- Do not reach into Svelte stores or Svelte context from legacy JS.
- Do not bypass the bridge by importing legacy modules into Svelte or Svelte stores into legacy modules.

### Events and calls

The bridge exposes a large set of `on*` (legacy → Svelte) and `trigger*`/`update*` (Svelte → legacy) methods. The full surface lives in `resources/js/legacy/OldUiBridge.svelte.ts` — open the class for the event list, payloads, and the suppression-while-sending rules. A few representative entries:

- `onClearActiveConversation(handler)`, `onLoadSystemPrompt(handler)`, `onLoadInitialModel(handler)` — the composer subscribes to these to reset / re-fill its state when the legacy layer loads or clears a conversation.
- `onEnterMode(handler)`, `onExitMode(handler)`, `onExitThread(handler)` — mode lifecycle driven by the legacy layer; suppressed while a send is in progress.
- `triggerSendMessage(payload)`, `triggerContextReady()`, `triggerExport(exportType)` — Svelte calls that drive legacy-side behaviour.
- `updateCurrentChatModelId(modelId)`, `updateActiveConversationSystemPrompt(prompt)` — Svelte pushes state changes to the legacy model selector / system-prompt editor.

`passkey` is a reactive `$state<string | null>` holding the user's decrypted passkey for the current session, populated by the legacy layer once the user unlocks.

If you find yourself calling `oldUiBridge.triggerSendToast` from one Svelte component to notify another Svelte component, introduce a dedicated store or context value instead. The bridge is for interop with the legacy layer only — using it for pure Svelte-to-Svelte communication ties new code to a construct that will be removed.

---

## OldUiMessageHistory

A companion singleton that holds the read-state of the active conversation. Import it:

```ts
import { oldUiMessageHistory } from '$lib/legacy/OldUiMessageHistory.svelte.ts';
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

### Mutation and Lookup Methods

The class also exposes `loadConversation`, `updateConversation`, `clearConversation`, `addMessageToConversation`, `updateMessageInConversation`, `removeMessageFromConversation`, `removeFileByUuid`, `findMessageById`, and `findMessageByAttachmentUuid`. The full surface lives in `OldUiMessageHistory.svelte.ts`; these are called by the legacy layer, not by new Svelte code.

### `canWrite` in Practice

Check `oldUiMessageHistory.canWrite` before enabling the composer's send button or the system-prompt editor. It is `false` for conversations the user can only view, and `true` for any conversation where the user is `admin`, `editor`, or the owner of a personal AI conversation (`aiConv` context type).
