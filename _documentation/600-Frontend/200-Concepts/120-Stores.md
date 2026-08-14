# Stores

Reactive state that needs to be shared across components lives in stores. Each store is a TypeScript class using Svelte 5 Runes (`$state`, `$derived`) that `implements DataStore`, with a `name` property and an optional `loadData(app)` method. Stores are registered by a plugin (the core plugin registers all of them today) into the kernel's `StoreExtension`, which drives `loadData` on the bootstrapper's `main` stage.

Source: `resources/js/plugins/core/stores/*.svelte.ts`. Registry: `app.stores` (see [The App & Kernel](../300-Architecture/100-App-and-Kernel.md)).

## Accessing a store

In a Svelte component, use the `useStore()` hook — it returns the typed store instance from `app.stores.get(name)`:

```svelte
<script lang="ts">
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';

    const aiModelStore = useStore('ai-models');
</script>

<p>{aiModelStore.models.length} models available</p>
```

In non-component code, reach the registry through the app:

```ts
import {getHawkiApp} from '$lib/legacy/legacy.js';

const themeStore = getHawkiApp().stores.get('theme');
```

The string key you pass is typed against `HawkiDataStores` (augmented next to each store class), so `useStore('theme')` returns a `ThemeStore`, not a generic `DataStore`. Passing an unknown name is a compile error.

All stores that implement `loadData(app)` have it invoked once, concurrently, during the `main` boot stage — so they are safe to read after `main` completes. The `keychain` store is the exception: it loads asynchronously once the user's passkey becomes available (see [Keychain Store](#keychainstore)).

---

## Store Overview

| Key | Class | What it holds |
|---|---|---|
| `'ai-models'` | `AiModelStore` | All available AI models and system-role assignments |
| `'ai-tools'` | `AiToolStore` | AI tools and capability definitions |
| `'system-prompts'` | `SystemPromptStore` | Server-configured system prompts |
| `'keychain'` | `KeychainStore` | User's encryption keys (async load) |
| `'theme'` | `ThemeStore` | Active UI theme (`'dark'` / `'light'`) |
| `'ai-handle'` | `AiHandleStore` | Configured `@handle` string for mention parsing |

---

## `AiModelStore`

Holds all AI models returned by the API. The source of truth for which models are available, which one is active, and which system-role assignments are configured.

```svelte
<script lang="ts">
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    const aiModelStore = useStore('ai-models');
</script>
```

### Key properties

| Property | Type | Description |
|---|---|---|
| `models` | `AiModel[]` | All available models in API order. Reactive. |
| `systemModels` | `Record<string, AiModel>` | System-role assignments keyed by type string. |

### `AiModel` shape

Notable fields on the `AiModel` type (from `$lib/plugins/core/schemas/resources/ai-models.schema.js`):

| Field | Type | Notes |
|---|---|---|
| `active` | `boolean` | Whether the model is enabled in the admin panel. |
| `model_type` | `'chat' \| 'image_generation' \| 'video_generation' \| null` | Discriminates the union — use to narrow to `ChatAiModel`. |
| `native_capabilities` | `string[]` \| `null` | Capability IDs the model supports natively (e.g. `'web_search'`). |
| `flags` | `string[]` \| `null` | Badge-style flags (e.g. `'eco-friendly'`, `'feature-streaming'`). Full list in `ai-model-flags.ts`. |
| `limits` | `{ max_input_tokens, max_output_tokens }` \| `null` | Token limits. Present only on `model_type === 'chat'` models. |
| `pricing` | `{ is_free } \| { ranges, priority_ranges }` \| `null` | Pricing info. Present only on `model_type === 'chat'` models. |

### Key methods

**`getOneById(modelId)`** — Accepts an `AiModel` object, a numeric ID, or a `model_id` string. Returns `null` when no match is found.

**`getModelByIdOrFallback(modelId, fallbackType?)`** — Like `getOneById` but never returns `null`. Falls back to the model assigned to `fallbackType` (default: `'default'`), then to the first available model. Use this when building a chat request and a concrete model is always required.

**`getSystemModelByType(type)`** — Returns the model assigned to a system role, or `null`.

```ts
const model = aiModelStore.getModelByIdOrFallback(selectedModelId);
const defaultModel = aiModelStore.getSystemModelByType('default');
```

---

## `AiToolStore`

Holds all registered AI tools and capabilities as a single merged list.

```svelte
<script lang="ts">
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    const aiToolStore = useStore('ai-tools');
</script>
```

### Key property

| Property | Type | Description |
|---|---|---|
| `tools` | `AiToolOrCapability[]` | All registered tools and capabilities, merged into one reactive list. |

### `AiToolOrCapability`

Each entry is either an `AiToolWrapper` (a plain tool) or an `AiToolCapabilityWrapper` (a capability that groups related tools). Discriminate with `is_capability`:

```ts
import type {AiToolOrCapability} from '$lib/plugins/core/stores/aiToolStoreData.js';

for (const item of aiToolStore.tools) {
    if (item.is_capability) {
        const toolsForModel = item.getToolsFor(currentModel);
        const isNative = item.hasNativeCapabilityFor(currentModel);
    } else {
        const available = item.isAvailableFor(currentModel);
    }
}
```

Both types share these members:

| Member | Notes |
|---|---|
| `is_capability` | `false` for plain tools, `true` for capability wrappers. |
| `displayName` | Localized display name (getter). |
| `isAvailableFor(model, withOffline?)` | `true` when the model supports this item. |

`AiToolCapabilityWrapper` additionally exposes `hasNativeCapabilityFor(model)`, `getTools()`, and `getToolsFor(model)`.

---

## `SystemPromptStore`

Holds the server-configured system prompts. Populated during bootstrap.

```svelte
<script lang="ts">
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    const systemPromptStore = useStore('system-prompts');
</script>
```

**`getPromptByType(type)`** — Looks up a prompt by `prompt_type`. When called with a `WellKnownSystemPromptType` constant the return type is non-nullable, eliminating a null-check at the call site.

```ts
const chatPrompt = systemPromptStore.getPromptByType('chat');
// chatPrompt is SystemPrompt (non-nullable for known type strings)
```

---

## `KeychainStore`

Exposes the user's end-to-end encryption keys as reactive `$state` properties. Loading is deferred until the user's passkey becomes available on the legacy bridge — `waitingToLoad` resolves when the initial load is complete.

```svelte
<script lang="ts">
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    const keychainStore = useStore('keychain');
</script>
```

| Property | Type | Description |
|---|---|---|
| `publicKey` | `CryptoKey \| null` | RSA public key. `null` until loaded. |
| `privateKey` | `CryptoKey \| null` | RSA private key. `null` until loaded. |
| `aiConvKey` | `CryptoKey \| null` | Shared AES key for AI conversations. `null` until loaded. |
| `roomKeys` | `Record<string, RoomKeys>` | Per-room keys keyed by slug. Empty until loaded. |
| `waitingToLoad` | `Promise<void>` | Resolves once the initial key load completes. Throws if accessed before `loadData` has run. |

It also exposes `validateKeychainPassword(passkey)`, `initializeNewKeychain()`, `createNewRoomKey(slug)`, and `importRoomKey(slug, key)` — the operations a feature surface (e.g. room-key management) needs. For the full crypto handle and its lower-level operations, see `kernel/keychain/keychainHandle.ts` and [Encryption](160-Encryption.md).

---

## `ThemeStore`

Tracks and controls the active UI theme. Reading `theme` inside a `$derived` or component template is reactive — the component re-renders when the theme changes.

```svelte
<script lang="ts">
    import {useStore} from '$lib/app/hooks/useStore.svelte.js';
    const themeStore = useStore('theme');

    const isDark = $derived(themeStore.theme === 'dark');
</script>

<button onclick={() => themeStore.theme = 'light'}>Light</button>
```

The store observes the `<html>` class list via a `MutationObserver`, so it stays in sync even when the theme is toggled by legacy code outside the Svelte layer.

---

## `AiHandleStore`

Provides the configured `@handle` string and a parser for detecting handle mentions in chat messages.

```ts
const aiHandleStore = useStore('ai-handle');

const handle = aiHandleStore.hawkiHandle;          // e.g. '@hawki'

for (const found of aiHandleStore.getHandlesIn(messageText)) {
    // found === '@hawki' (only currently known handle)
}
```

`getHandlesIn(message)` is a generator that yields each recognized handle found in the string. Currently only the single configured HAWKI handle is matched, but the method is structured to support additional handles once assistant personas are introduced.

---

## Writing a store

1. Create a `.svelte.ts` class under your plugin's `stores/` directory that `implements DataStore` with a readonly `name`:

```ts
// resources/js/plugins/myPlugin/stores/MyStore.svelte.ts
import type {DataStore} from '$lib/kernel/stores/types.js';
import type {HawkiApp} from '$lib/kernel/HawkiApp.js';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'my-thing': MyStore;
    }
}

export class MyStore implements DataStore {
    public readonly name = 'my-thing';
    public count = $state(0);

    public async loadData(app: HawkiApp) {
        // Fetch/hydrate from the API. Runs once on the 'main' boot stage.
        // Omit this method entirely if the store has no server data to load.
    }
}
```

2. Register it from your plugin's `stores()` hook (see [Extending HAWKI](../../700-Extending-Hawki/index.md)):

```ts
public stores({add}: StoreRegistrar): void | Promise<void> {
    add(new MyStore());
}
```

That's the whole wiring — the `name` becomes the `useStore('my-thing')` key, and `loadData` is called automatically on the `main` stage if present.
