# UI Primitives

Low-level primitive components with no business logic and no dependency on app state or domain types. Each is a focused, composable building block modelled after the shadcn/ui pattern. Compose them into higher-level components in `components/`; snippets should not import directly from `ui/` unless the usage is trivially simple.

## Available Primitives

| Component(s)                            | Directory / File      | Purpose                                                                                 |
|-----------------------------------------|-----------------------|-----------------------------------------------------------------------------------------|
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
| `Tooltip`                               | `ui/tooltip/`         | Floating tooltip                                                                        |
| `Toaster` + `ToastContext`              | `ui/toast/`           | Toast notification system — see below                                                   |
| `Badge`                                 | `ui/badge/`           | Label/badge chip                                                                        |
| `RadialProgress`                        | `ui/radial-progress/` | Circular progress indicator                                                             |
| `BorderBeam`                            | `ui/border-beam/`     | Animated border highlight effect                                                        |
| `StatusDot`                             | `ui/status-dot/`      | Colored status indicator dot                                                            |
| `Separator`                             | `ui/separator/`       | Visual divider line                                                                     |
| `RadioRow`, `RadioRowGroup`             | `ui/radio-row/`       | Radio group of flat menu rows with sliding highlights and a spring-animated indicator   |
| `Citation`, `CitationList`, `CitationReference`, `CitationRoot` | `ui/citations/` | Web-search citation tiles and inline reference chips rendered below AI messages |

---

## Toasts

The toast system consists of two parts: the `Toaster` component (rendered once by the `LegacySharedContent` snippet — a core-plugin snippet auto-injected on every page) and `ToastContext`, which any component uses to push notifications.

```svelte
<script lang="ts">
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    const toast = useToastContext();
</script>

<button onclick={() => toast.success('Saved!')}>Save</button>
<button onclick={() => toast.error('Something went wrong.')}>Fail</button>
<button onclick={() => toast.info('Processing…')}>Info</button>
```

`ToastContext` is set up by the `LegacySharedContent` snippet (registered by the core plugin and auto-injected on every page). Do not instantiate `Toaster` yourself.

---

## RadioRow

`RadioRowGroup` + `RadioRow` implement a radio group of flat menu rows. Bind `value` on the group; each row's `value` prop identifies it. The rows sit in a `MenuList`, so the selection and the pointer get its sliding highlights.

```svelte
<script lang="ts">
    import RadioRowGroup from '$lib/components/ui/radio-row/RadioRowGroup.svelte';
    import RadioRow from '$lib/components/ui/radio-row/RadioRow.svelte';
    import InfoPopover from '$lib/components/ui/popover/InfoPopover.svelte';

    let selected = $state('a');
</script>

<RadioRowGroup bind:value={selected} name="my-group">
    <RadioRow value="a">Option A</RadioRow>
    <RadioRow value="b">
        Option B
        {#snippet trailing()}<InfoPopover info="What option B does"/>{/snippet}
    </RadioRow>
    <RadioRow value="c" disabled>Option C (disabled)</RadioRow>
</RadioRowGroup>
```

**`RadioRowGroup` props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `value` | `string` | `''` | The selected row's value. Bindable. |
| `disabled` | `boolean` | `false` | Disables every row in the group. |
| `name` | `string` | — | Shared `name` for the underlying radio inputs. |
| `onChange` | `(value: string) => void` | — | Called with the newly selected value. |

**`RadioRow` props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `value` | `string` | — | The value this row represents. |
| `disabled` | `boolean` | `false` | Disables this row individually. |
| `children` | `Snippet` | — | Row label. |
| `trailing` | `Snippet` | — | Content at the row's end edge (status dots, an info button). It sits outside the radio, so its controls never select the row. |

Selection is animated with a spring-driven dot indicator. The group is a single Tab stop (the selected row); ArrowUp/ArrowDown move to and select the previous/next enabled row, Home/End jump to the ends, and Space/Enter select the focused row. The group carries `role="radiogroup"` and each row's control `role="radio"`.

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
2. Follow the patterns in [Writing Svelte Components](index.md) — `Props extends HTMLAttributes<…>`, `mergeProps` for rest-prop forwarding, `@component` block comment.
3. Build on `bits-ui` primitives where one fits (dialogs, popovers, selects, tooltips, etc.) — they handle accessibility and keyboard navigation.
4. Style with the CSS token system described in [Styling](../200-Styling.md). No Tailwind, no hard-coded values.
