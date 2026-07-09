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
| `RadioCard`, `RadioCardGroup`           | `ui/radio-card/`      | Card-style radio group — each card is selectable with a spring-animated indicator       |
| `Citation`, `CitationList`, `CitationReference`, `CitationRoot` | `ui/citations/` | Web-search citation tiles and inline reference chips rendered below AI messages |

---

## Toasts

The toast system consists of two parts: the `Toaster` component (rendered once by `LegacySharedContent.svelte`) and `ToastContext`, which any component uses to push notifications.

```svelte
<script lang="ts">
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    const toast = useToastContext();
</script>

<button onclick={() => toast.success('Saved!')}>Save</button>
<button onclick={() => toast.error('Something went wrong.')}>Fail</button>
<button onclick={() => toast.info('Processing…')}>Info</button>
```

`ToastContext` is set up by the `LegacySharedContent` snippet which is auto-injected on every page. Do not instantiate `Toaster` yourself.

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

**`RadioCardGroup` props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `value` | `string` | `''` | The selected card's value. Bindable. |
| `disabled` | `boolean` | `false` | Disables (and dims) every card in the group. |
| `name` | `string` | — | Shared `name` for the underlying radio inputs. |
| `onChange` | `(value: string) => void` | — | Called with the newly selected value. |

**`RadioCard` props:**

| Prop | Type | Default | Description |
|---|---|---|---|
| `value` | `string` | — | The value this card represents. |
| `disabled` | `boolean` | `false` | Disables this card individually. |
| `children` | `Snippet` | — | Card content. |

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

`injectCitationsIntoMarkdown` (in `$lib/components/chat/message/injectCitationsIntoMarkdown.ts`) pre-processes a markdown string and rewrites citation ranges into anchor links that `ExtendedLinkNode` turns into `CitationReference` chips.

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
