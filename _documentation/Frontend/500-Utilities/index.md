# Misc Utilities

Small, focused helpers used across the frontend. All are importable directly from their respective modules under `$lib/utils/`.

---

## `debounce(func, wait)` — `$lib/utils/debounce.js`

Returns a debounced version of `func` that delays invoking it until `wait` milliseconds have elapsed since the last call. Repeated calls within the window reset the timer.

```ts
import { debounce } from '$lib/utils/debounce.js';

const search = debounce((query: string) => fetchResults(query), 300);
input.addEventListener('input', (e) => search(e.target.value));
```

---

## `buildStorageFileUrl(fileIdentifier)` — `$lib/utils/storageFileProxy.js`

Builds the proxied URL for a file stored in the backend file storage. The browser fetches the file through the HAWKI backend rather than hitting the storage provider directly. Returns `null` when `fileIdentifier` is falsy.

```ts
import { buildStorageFileUrl } from '$lib/utils/storageFileProxy.js';

const url = buildStorageFileUrl(attachment.file_identifier);
if (url) {
    img.src = url;
}
```

Use this wherever an attachment or user-uploaded file needs to be displayed or downloaded.

---

## `getFileIconSvg(extension)` — `$lib/utils/fileIconSvg.js`

Returns a `data:image/svg+xml,...` URL for a file-type icon badge. The icon is a document shape with the uppercased extension label centred on a colour-coded banner. The banner colour is derived deterministically from the extension string, so the same extension always produces the same colour.

```ts
import { getFileIconSvg } from '$lib/utils/fileIconSvg.js';

img.src = getFileIconSvg('pdf');   // orange-ish banner, 'PDF' label
img.src = getFileIconSvg('docx');  // different colour, 'DOCX' label
```

Use this for attachment thumbnails when a file has no image preview.

---

## AI Tool Display Helpers — `$lib/utils/aiToolUtils.js`

Two helpers for rendering AI tool metadata in the UI. Both resolve the tool's linked capability first to get the translated label, and fall back to a humanised version of the raw tool name so they are always safe to call without null-checks.

```ts
import { toolDisplayName, toolDisplayDescription } from '$lib/utils/aiToolUtils.js';

// In a template
<span>{toolDisplayName(tool)}</span>
{#if toolDisplayDescription(tool)}
    <p>{toolDisplayDescription(tool)}</p>
{/if}
```

**`toolDisplayName(tool)`** — Returns a translated display name for the tool. Falls back to the tool name converted to Title Case.

**`toolDisplayDescription(tool)`** — Returns a translated description string, or `null` when neither a capability description nor a raw `tool.description` is available.

---

## `growTransition` — `$lib/utils/transitions/growTransition.js`

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

| Parameter | Values | Default | Description |
|---|---|---|---|
| `direction` | `'in'` \| `'out'` | `'in'` | Enter uses a gentle spring overshoot; leave uses `cubicOut`. |
| `mode` | `'vertical'` \| `'horizontal'` | `'vertical'` | Which dimension to animate. |

---

## Component Prop Type Helpers — `$lib/utils/utils.js`

Four TypeScript utility types for working with `bits-ui` primitive props:

```ts
import type {
    WithoutChild,
    WithoutChildren,
    WithoutChildrenOrChild,
    WithElementRef
} from '$lib/utils/utils.js';
```

| Type | Purpose |
|---|---|
| `WithoutChild<T>` | Strips the `child` snippet prop from a bits-ui props type |
| `WithoutChildren<T>` | Strips the `children` snippet prop |
| `WithoutChildrenOrChild<T>` | Strips both |
| `WithElementRef<T, U>` | Adds an optional `ref` binding so a parent can hold a reference to the underlying DOM element |

```ts
// Expose a ref on a wrapped primitive
import type { WithElementRef } from '$lib/utils/utils.js';
import type { HTMLButtonAttributes } from 'svelte/elements';

interface Props extends WithElementRef<HTMLButtonAttributes, HTMLButtonElement> {}
const { ref = $bindable(), ...rest }: Props = $props();
```

```svelte
<button bind:this={ref} {...rest} />
```
