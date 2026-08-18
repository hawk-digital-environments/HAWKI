# UI Primitives

Low-level primitive components with no business logic and no dependency on app state or domain types. Each is a focused, composable building block modelled after the shadcn/ui pattern. Compose them into higher-level components in feature modules; pages should not import directly from `ui/` unless the usage is trivially simple.

## Available Primitives

| Component(s)                            | Directory / File      | Purpose                                                                                 |
|-----------------------------------------|-----------------------|-----------------------------------------------------------------------------------------|
| `Alert`                                 | `ui/alert/Alert.svelte` | Inline banner for a titled message with an optional leading icon (validation summary, destructive-action warning) |
| `Button`, `ButtonWithTooltip`           | `ui/button/`          | Standard button and a button with an attached tooltip                                   |
| `Txt`                                   | `ui/Txt.svelte`       | Typography primitive with a semantic variant prop                                       |
| `Dialog`, `ConfirmDialog`, `InfoDialog` | `ui/dialog/`          | Modal dialogs — generic, confirm-action, and informational variants                     |
| `DropdownMenu` + items                  | `ui/dropdown-menu/`   | Full dropdown composition: groups, separators, checkbox/radio/switch items, detail view |
| `Popover`, `InfoPopover`                | `ui/popover/`         | Floating popover and a pre-styled info variant                                          |
| `SingleSelect`                          | `ui/select/`          | Styled single-value select input                                                        |
| `BottomSheet`                           | `ui/sheet/`           | Mobile-friendly bottom drawer                                                           |
| `Slider`                                | `ui/slider/`          | Range input slider                                                                      |
| `Switch`                                | `ui/switch/`          | Toggle switch                                                                           |
| `Tabs`                                  | `ui/tabs/`            | Tab navigation                                                                          |
| `Textarea`                              | `ui/textarea/Textarea.svelte` | Resizable multi-line text input. Forwards all native `<textarea>` attributes, supports `bind:value`. |
| `Tooltip`                               | `ui/tooltip/`         | Floating tooltip                                                                        |
| `Toaster` + `ToastContext`              | `ui/toast/`           | Toast notification system — see below                                                   |
| `Badge`                                 | `ui/badge/`           | Label/badge chip                                                                        |
| `RadialProgress`                        | `ui/radial-progress/` | Circular progress indicator                                                             |
| `BorderBeam`                            | `ui/border-beam/`     | Animated border highlight effect                                                        |
| `StatusDot`                             | `ui/status-dot/`      | Colored status indicator dot                                                            |
| `Separator`                             | `ui/separator/`       | Visual divider line                                                                     |
| `RadioCard`, `RadioCardGroup`           | `ui/radio-card/`      | Card-style radio group — each card is selectable with a spring-animated indicator       |
| `Citation`, `CitationList`, `CitationReference`, `CitationRoot` | `ui/citations/` | Web-search citation tiles and inline reference chips rendered below AI messages |
| `Loader`                                | `ui/loader/Loader.svelte` | Swaps its `children` for a spinner while `active` is true — replace content in place rather than overlaying |
| `RouterView`, `RouteError`, `RouteNotFound` + routing kit | `ui/routing/` | The SPA routing kit: `RouterView` renders the matched route nested in its layout stack; `RouteError`/`RouteNotFound` handle failures. The public surface (barrel `index.ts`, `configurePage`, `useRouter`, strategies) is documented in [Concepts → Routing](../200-Concepts/190-Routing.md); see [Modules & Routing](../200-Concepts/120-App-and-Kernel/120-Routing-and-Shell.md) for the architecture-level view. |

---

## Toasts

The toast system consists of two parts: the `Toaster` component (rendered once by the `LegacySharedContent` snippet on legacy pages, and set up via `createToastContext()` in `Shell.svelte` on SPA pages) and `ToastContext`, which any component uses to push notifications.

```svelte
<script lang="ts">
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    const toast = useToastContext();
</script>

<button onclick={() => toast.success('Saved!')}>Save</button>
<button onclick={() => toast.error('Something went wrong.')}>Fail</button>
<button onclick={() => toast.info('Processing…')}>Info</button>
```

`ToastContext` is set up by `Shell` (SPA pages) and by the `LegacySharedContent` snippet (legacy pages). Do not instantiate `Toaster` yourself.

---

## RadioCard

`RadioCardGroup` + `RadioCard` implement a card-style radio group. Bind `value` on the group; each card's `value` prop identifies it.

```svelte
<script lang="ts">
    import RadioCardGroup from '$lib/components/ui/radio-card/RadioCardGroup.svelte';
    import RadioCard from '$lib/components/ui/radio-card/RadioCard.svelte';

    let selected = $state('a');
</script>

<RadioCardGroup bind:value={selected} name="my-group">
    <RadioCard value="a">Option A</RadioCard>
    <RadioCard value="b">Option B</RadioCard>
    <RadioCard value="c" disabled>Option C (disabled)</RadioCard>
</RadioCardGroup>
```

Selection is animated with a spring-driven dot indicator. Cards are keyboard-reachable with Space/Enter and carry full ARIA `role="radio"` / `role="radiogroup"` semantics.

---

## Citations

The citation system renders web-search sources below an AI message as a grid of tiles, and wires inline numbered chips in the message body to scroll and flash-highlight the matching tile.

Four components work together:

| Component | Role |
|---|---|
| `CitationRoot` | Wraps the entire message + citation area; sets up the shared `CitationContext`. |
| `CitationList` | Renders the "Sources" heading and the tile grid. Place it after the message body. |
| `Citation` | A single source tile — displays favicon, domain, and source number; scrolls and flashes when its chip is clicked. |
| `CitationReference` | An inline chip (used inside rendered markdown) that scrolls to the matching `Citation` tile when clicked. |

`injectCitationsIntoMarkdown` (in `$plugins/core/modules/chat/components/message/injectCitationsIntoMarkdown.ts`) pre-processes a markdown string and rewrites citation ranges into anchor links that `ExtendedLinkNode` turns into `CitationReference` chips.

Typical assembly:

```svelte
<CitationRoot>
    <!-- Rendered message body (uses ExtendedLinkNode via Markdown component) -->
    <Markdown message={body} />

    <!-- Source tiles -->
    <CitationList>
        {#each citations as citation, i}
            <Citation {citation} number={i + 1} />
        {/each}
    </CitationList>
</CitationRoot>
```

`Citation` expects an `EnrichedUrlCitation` (`{ url, title, ranges, identifier }` from `$lib/components/ui/citations/types.js`). The `identifier` field is the stable key that links a tile to its inline chips.

---

## Adding a New Primitive

When porting or writing a new primitive:

1. Create a directory in `components/ui/` named after the component (kebab-case).
2. Follow the patterns in [Svelte Components](../200-Concepts/100-Svelte-Components.md) — `Props extends HTMLAttributes<…>`, `mergeProps` for rest-prop forwarding, `@component` block comment.
3. Build on `bits-ui` primitives where one fits (dialogs, popovers, selects, tooltips, etc.) — they handle accessibility and keyboard navigation.
4. Style with the CSS token system described in [Styling](../200-Concepts/110-Styling.md). No Tailwind, no hard-coded values.
