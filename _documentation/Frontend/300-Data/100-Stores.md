# Stores

Reactive state that needs to be shared across components lives in `resources/js/stores/`. Each store is a TypeScript class using Svelte 5 Runes (`$state`, `$derived`) exported as a module-level singleton. Because JavaScript modules are singletons within a page load, every component that imports the same store reads from and writes to the same reactive instance — no prop-drilling or context setup required. Store files use the `.svelte.ts` extension so the Svelte compiler processes the runes.

Each store file exports both the class and a pre-constructed singleton:

```ts
// resources/js/stores/MyStore.svelte.ts
export class MyStore {
    public count = $state(0);
    public doubled = $derived(this.count * 2);
}

export const myStore = new MyStore();
```

Import the singleton in any component or utility:

```svelte
<script lang="ts">
    import {myStore} from '$lib/stores/MyStore.svelte.js';
</script>

<p>Count: {myStore.count}</p>
```

> Note the `.js` extension in imports — Vite resolves `.svelte.ts` files when a `.js` extension is used, which is the standard TypeScript ESM convention.

All stores are populated during the bootstrap sequence and are safe to read after the `main` stage completes. The `keychainStore` is the exception — it loads asynchronously once the user's passkey becomes available (see [Keychain Store](#keychainstore) below).

```ts
import { aiModelStore } from '$lib/stores/AiModelStore.svelte.js';

// Inside a component or $derived — reactive, updates automatically
const models = $derived(aiModelStore.models);
```

---

## Store Overview

| Store | Import | What it holds |
|---|---|---|
| `aiModelStore` | `$lib/stores/AiModelStore.svelte.js` | All available AI models and system-role assignments |
| `aiToolStore` | `$lib/stores/AiToolStore.svelte.js` | AI tools and capability definitions |
| `systemPromptStore` | `$lib/stores/SystemPromptStore.svelte.js` | Server-configured system prompts |
| `keychainStore` | `$lib/stores/KeychainStore.svelte.js` | User's encryption keys (async load) |
| `themeStore` | `$lib/stores/ThemeStore.svelte.js` | Active UI theme (`'dark'` / `'light'`) |
| `aiHandleStore` | `$lib/stores/AiHandleStore.svelte.js` | Configured `@handle` string for mention parsing |

---

## `AiModelStore`

Holds all AI models returned by the API. The most commonly used store — it is the source of truth for which models are available, which one is active, and which system-role assignments are configured.

```ts
import { aiModelStore } from '$lib/stores/AiModelStore.svelte.js';
```

### Key properties

| Property | Type | Description |
|---|---|---|
| `models` | `AiModel[]` | All available models in API order. Reactive. |
| `systemModels` | `Record<string, AiModel>` | System-role assignments keyed by type string. |

### Key methods

**`getOneById(modelId)`** — Accepts an `AiModel` object, a numeric ID, or a `model_id` string. Returns `null` when no match is found.

**`getModelByIdOrFallback(modelId, fallbackType?)`** — Like `getOneById` but never returns `null`. Falls back to the model assigned to `fallbackType` (default: `'default'`), then to the first available model. Use this when building a chat request and a concrete model is always required.

**`getSystemModelByType(type)`** — Returns the model assigned to a system role, or `null`.

```ts
// Get the model currently selected (with safe fallback)
const model = aiModelStore.getModelByIdOrFallback(selectedModelId);

// Get the system default
const defaultModel = aiModelStore.getSystemModelByType('default');
```

---

## `AiToolStore`

Holds all registered AI tools and their associated capability definitions. Use the helper methods rather than filtering `tools` or reading `model.tool_ids` directly — the helpers encapsulate the capability resolution logic.

```ts
import { aiToolStore } from '$lib/stores/AiToolStore.svelte.js';
```

### Key properties

| Property | Type | Description |
|---|---|---|
| `tools` | `AiTool[]` | All registered tools. Reactive. |
| `capabilities` | `AiToolCapability[]` | All capability definitions. Reactive. |

### Key methods

**`availableToolsForModel(model)`** — Returns the subset of tools the given model supports. Use this to populate a tool picker.

**`isAvailableForModel(tool, model)`** — Returns `true` when a specific tool (object or name string) is enabled for the model.

**`isAvailableCapabilityOfModel(capability, model)`** — Returns `true` when a capability is enabled for the model, respecting per-model overrides and the capability's `default_value`.

**`availableCapabilitiesForModel(model)`** — Returns the enabled capabilities for the model.

**`getCapabilityForTool(tool)`** — Returns the `AiToolCapability` linked to a tool via its `capability_key`, or `null`.

```ts
// Show only tools the active model supports
const tools = $derived(aiToolStore.availableToolsForModel(currentModel));

// Gate on a capability before sending
if (aiToolStore.isAvailableCapabilityOfModel('code-interpreter', currentModel)) {
    // ...
}
```

---

## `SystemPromptStore`

Holds the server-configured system prompts. Populated during bootstrap.

```ts
import { systemPromptStore } from '$lib/stores/SystemPromptStore.svelte.js';
```

**`getPromptByType(type)`** — Looks up a prompt by `prompt_type`. When called with a `WellKnownSystemPromptType` constant the return type is non-nullable, eliminating a null-check at the call site.

```ts
const chatPrompt = systemPromptStore.getPromptByType('chat');
// chatPrompt is SystemPrompt (non-nullable for known type strings)
```

---

## `KeychainStore`

Exposes the user's end-to-end encryption keys as reactive `$state` properties. Loading is deferred until the user's passkey becomes available on the legacy bridge — `waitingToLoad` resolves when the initial load is complete.

```ts
import { keychainStore } from '$lib/stores/KeychainStore.svelte.js';
```

| Property | Type | Description |
|---|---|---|
| `publicKey` | `CryptoKey \| null` | RSA public key. `null` until loaded. |
| `privateKey` | `CryptoKey \| null` | RSA private key. `null` until loaded. |
| `aiConvKey` | `CryptoKey \| null` | Shared AES key for AI conversations. `null` until loaded. |
| `roomKeys` | `Record<string, RoomKeys>` | Per-room keys keyed by slug. Empty until loaded. |
| `waitingToLoad` | `Promise<void>` | Resolves once the initial key load completes. |

For cryptographic operations involving these keys, see the **Encryption** section in Advanced.

---

## `ThemeStore`

Tracks and controls the active UI theme. Reading `theme` inside a `$derived` or component template is reactive — the component re-renders when the theme changes.

```ts
import { themeStore } from '$lib/stores/ThemeStore.svelte.js';

// Read
const isDark = $derived(themeStore.theme === 'dark');

// Write (updates <html> class list + reactive value in one step)
themeStore.theme = 'light';
```

The store observes the `<html>` class list via a `MutationObserver`, so it stays in sync even when the theme is toggled by legacy code outside the Svelte layer.

---

## `AiHandleStore`

Provides the configured `@handle` string and a parser for detecting handle mentions in chat messages.

```ts
import { aiHandleStore } from '$lib/stores/AiHandleStore.svelte.js';

// The configured handle string (e.g. '@hawki')
const handle = aiHandleStore.hawkiHandle;

// Detect mentions in a message
for (const found of aiHandleStore.getHandlesIn(messageText)) {
    // found === '@hawki' (only currently known handle)
}
```

`getHandlesIn(message)` is a generator. It yields each recognized handle found in the string. Currently only the single configured HAWKI handle is matched, but the method is structured to support additional handles once assistant personas are introduced.
