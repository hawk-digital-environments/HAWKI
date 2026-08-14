# Utilities

Small, focused helpers used across the frontend. All are importable directly from their respective modules under `$lib/utils/`.

---

## `debounce(func, wait)` — `$lib/utils/debounce.ts`

Returns a debounced version of `func` that delays invoking it until `wait` milliseconds have elapsed since the last call. Repeated calls within the window reset the timer.

```ts
import {debounce} from '$lib/utils/debounce.js';

const search = debounce((query: string) => fetchResults(query), 300);
input.addEventListener('input', (e) => search(e.target.value));
```

---

## `buildStorageFileUrl(fileIdentifier)` — `$lib/utils/storageFileProxy.ts`

:::danger[Deprecated]
`buildStorageFileUrl` is deprecated. Use `UriBuilder.storageFileUri` (reached via `useApp().uriBuilder` or `useRestApi()`'s `uriBuilder`) instead.
:::

Builds the proxied URL for a file stored in the backend file storage. The browser fetches the file through the HAWKI backend rather than hitting the storage provider directly. Returns `null` when `fileIdentifier` is falsy.

```ts
import {buildStorageFileUrl} from '$lib/utils/storageFileProxy.js';

const url = buildStorageFileUrl(attachment.file_identifier);
if (url) {
    img.src = url;
}
```

Published to legacy code as `window.buildStorageFileUrl`.

---

## `getFileIconSvg(extension)` — `$lib/utils/fileIconSvg.ts`

Returns a `data:image/svg+xml,...` URL for a file-type icon badge. The icon is a document shape with the uppercased extension label centred on a colour-coded banner. The banner colour is derived deterministically from the extension string, so the same extension always produces the same colour.

```ts
import {getFileIconSvg} from '$lib/utils/fileIconSvg.js';

img.src = getFileIconSvg('pdf');   // orange-ish banner, 'PDF' label
img.src = getFileIconSvg('docx');  // different colour, 'DOCX' label
```

Use this for attachment thumbnails when a file has no image preview. Published to legacy code as `window.getFileIconSvg`.

---

## `growTransition` — `$lib/utils/transitions/growTransition.ts`

A Svelte CSS transition that expands or collapses an element by animating its height or width from 0 to its natural size, fading opacity and scaling padding/margin proportionally so the element doesn't jump.

```svelte
<script lang="ts">
    import { growTransition } from '$lib/utils/transitions/growTransition.js';
    let visible = $state(false);
</script>

<!-- Vertical grow (default) -->
{#if visible}
    <div transition:growTransition>…</div>
{/if}

<!-- Horizontal grow, enter only -->
<span in:growTransition={{mode: 'horizontal'}}>…</span>
```

| Parameter   | Values                         | Default      | Description                                                  |
|-------------|--------------------------------|--------------|--------------------------------------------------------------|
| `direction` | `'in'` \| `'out'`              | `'in'`       | Enter uses a gentle spring overshoot; leave uses `cubicOut`. |
| `mode`      | `'vertical'` \| `'horizontal'` | `'vertical'` | Which dimension to animate.                                  |

---

## String helpers — `$lib/utils/strings.ts`

Small string utilities mirroring PHP helpers the backend uses:

| Function | Purpose |
|---|---|
| `ucfirst(str)` | Capitalise the first character (mirrors PHP's `Str::ucfirst`). |
| `strtr(str, pairs)` | Replace multiple substrings in one pass, preferring longer keys over shorter ones (mirrors PHP's `strtr`). |
| `basename(path)` | The filename portion of a path. |
| `valueToSlug(value)` | Convert a display value to a URL-safe slug. |

```ts
import {ucfirst, strtr} from '$lib/utils/strings.js';

ucfirst('alice');              // → 'Alice'
strtr('Hello :name', {':name': 'bob'});  // → 'Hello bob'
```

---

## `globModuleLoader` — `$lib/utils/globModuleLoader.ts`

A helper that turns a Vite `import.meta.glob()` result into a typed `Map` of loaded modules, applying a `keyResolver` (derive the map key from each file path), `valueKey` (which export to treat as the value), and `validate` guard. Used by the kernel's eager-glob registries (`ResourceSchemaExtension`, `ConfigurationExtension`) and the migration registrar. Throw inside `keyResolver` to abort on an unexpected filename shape.

See the file for the full options interface; the kernel extensions are the canonical consumers.

---

## Component Prop Type Helpers — `$lib/utils/utils.ts`

Four TypeScript utility types for working with `bits-ui` primitive props:

```ts
import type {
    WithoutChild,
    WithoutChildren,
    WithoutChildrenOrChild,
    WithElementRef
} from '$lib/utils/utils.js';
```

| Type                        | Purpose                                                                                       |
|-----------------------------|-----------------------------------------------------------------------------------------------|
| `WithoutChild<T>`           | Strips the `child` snippet prop from a bits-ui props type                                     |
| `WithoutChildren<T>`        | Strips the `children` snippet prop                                                            |
| `WithoutChildrenOrChild<T>` | Strips both                                                                                   |
| `WithElementRef<T, U>`      | Adds an optional `ref` binding so a parent can hold a reference to the underlying DOM element |

```ts
// Expose a ref on a wrapped primitive
import type {WithElementRef} from '$lib/utils/utils.js';
import type {HTMLButtonAttributes} from 'svelte/elements';

interface Props extends WithElementRef<HTMLButtonAttributes, HTMLButtonElement> {
}

const {ref = $bindable(), ...rest}: Props = $props();
```

```svelte
<button bind:this={ref} {...rest} />
```

---

## Migration helpers (deprecated) — `$lib/kernel/migrations/helpers.ts`

:::danger[Deprecated]
Both helpers are deprecated. Use `useApp().migration.hasPending` / `useApp().migration.apply(runType)` instead. They remain only to support legacy code and are published to `window.*` by `provideLegacyGlobals()`.
:::

| Function | Replaced by |
|---|---|
| `hasPendingMigrations()` | `useApp().migration.hasPending` |
| `applyMigrations(runType)` | `useApp().migration.apply(runType)` |

See [Concepts → Frontend Migrations](../200-Concepts/170-Frontend-Migrations.md) for the migration system.
