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

## Adding a New Primitive

When porting or writing a new primitive:

1. Create a directory in `components/ui/` named after the component (kebab-case).
2. Follow the patterns in [Writing Svelte Components](index.md) — `Props extends HTMLAttributes<…>`, `mergeProps` for rest-prop forwarding, `@component` block comment.
3. Build on `bits-ui` primitives where one fits (dialogs, popovers, selects, tooltips, etc.) — they handle accessibility and keyboard navigation.
4. Style with the CSS token system described in [Styling](../200-Styling.md). No Tailwind, no hard-coded values.
