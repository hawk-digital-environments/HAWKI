/**
 * Public entry point of the `@hawk-hhg/hawki-svelte-components` package.
 *
 * Everything re-exported here is considered public API. Consumers outside of
 * this directory MUST import from the alias root:
 *
 * ```ts
 * import {Button, Dialog, type IconComponent} from '@hawk-hhg/hawki-svelte-components';
 * ```
 *
 * The only exception are the generated icons in `ui/icons/iconset/`. They are
 * deliberately NOT part of this barrel — re-exporting ~6.1k components would
 * force every consumer to pull in the whole set. Import them through their
 * subpath instead:
 *
 * ```ts
 * import Cancel01Icon from '@hawk-hhg/hawki-svelte-components/ui/icons/iconset/Cancel01Icon.svelte';
 * ```
 *
 * Inside this directory all imports are relative, so the folder stays
 * self-contained and can be extracted into a real package later on.
 */

// =========================================================================
// ui
// =========================================================================

export {default as Txt} from './ui/Txt.svelte';

// -------------------------------------------------------------------------
// ui/alert
// -------------------------------------------------------------------------
export {default as Alert} from './ui/alert/Alert.svelte';

// -------------------------------------------------------------------------
// ui/badge
// -------------------------------------------------------------------------
export {default as Badge} from './ui/badge/Badge.svelte';
export {badgeVariants, type BadgeVariant} from './ui/badge/variants.js';

// -------------------------------------------------------------------------
// ui/border-beam
// -------------------------------------------------------------------------
export {default as BorderBeam} from './ui/border-beam/BorderBeam.svelte';
export {generateBeamCSS, sizePresets, sizeThemePresets} from './ui/border-beam/styles.js';
export type {BorderBeamSize, BorderBeamTheme, SizeConfig, ThemeColors} from './ui/border-beam/types.js';

// -------------------------------------------------------------------------
// ui/button
// -------------------------------------------------------------------------
export {default as Button} from './ui/button/Button.svelte';
export {buttonVariants, type ButtonSize, type ButtonVariant} from './ui/button/variants.js';
export {default as ButtonWithTooltip} from './ui/button/ButtonWithTooltip.svelte';

// -------------------------------------------------------------------------
// ui/citations
// -------------------------------------------------------------------------
export {default as Citation} from './ui/citations/Citation.svelte';
export {default as CitationList} from './ui/citations/CitationList.svelte';
export {default as CitationReference} from './ui/citations/CitationReference.svelte';
export {default as CitationRoot} from './ui/citations/CitationRoot.svelte';
export {CitationContext, createCitationContext, useCitationContext} from './ui/citations/CitationContext.js';
export type {EnrichedUrlCitation, UrlCitation} from './ui/citations/types.js';
export {CITATION_ANCHOR_PREFIX, citationAnchorId, citationIdFromAnchorId} from './ui/citations/anchors.js';

// -------------------------------------------------------------------------
// ui/dialog
// -------------------------------------------------------------------------
export {default as ConfirmDialog} from './ui/dialog/ConfirmDialog.svelte';
export {default as Dialog} from './ui/dialog/Dialog.svelte';
export {default as InfoDialog} from './ui/dialog/InfoDialog.svelte';

// -------------------------------------------------------------------------
// ui/dropdown-menu
// -------------------------------------------------------------------------
export {default as DropdownMenu} from './ui/dropdown-menu/DropdownMenu.svelte';
export {default as DropdownMenuCheckboxItem} from './ui/dropdown-menu/DropdownMenuCheckboxItem.svelte';
export {default as DropdownMenuDetailView} from './ui/dropdown-menu/DropdownMenuDetailView.svelte';
export {default as DropdownMenuGroup} from './ui/dropdown-menu/DropdownMenuGroup.svelte';
export {default as DropdownMenuItem} from './ui/dropdown-menu/DropdownMenuItem.svelte';
export {default as DropdownMenuLabel} from './ui/dropdown-menu/DropdownMenuLabel.svelte';
export {default as DropdownMenuRadioGroup} from './ui/dropdown-menu/DropdownMenuRadioGroup.svelte';
export {default as DropdownMenuRadioItem} from './ui/dropdown-menu/DropdownMenuRadioItem.svelte';
export {default as DropdownMenuSeparator} from './ui/dropdown-menu/DropdownMenuSeparator.svelte';
export {default as DropdownMenuSwitchItem} from './ui/dropdown-menu/DropdownMenuSwitchItem.svelte';

// -------------------------------------------------------------------------
// ui/icons
//
// The generated `iconset/` components are NOT exported here on purpose;
// import them via `@hawk-hhg/hawki-svelte-components/ui/icons/iconset/*.svelte`.
// -------------------------------------------------------------------------
export type {IconComponent} from './ui/icons/index.js';

// -------------------------------------------------------------------------
// ui/loader
// -------------------------------------------------------------------------
export {default as Loader} from './ui/loader/Loader.svelte';

// -------------------------------------------------------------------------
// ui/popover
// -------------------------------------------------------------------------
export {default as InfoPopover} from './ui/popover/InfoPopover.svelte';
export {default as Popover} from './ui/popover/Popover.svelte';

// -------------------------------------------------------------------------
// ui/radial-progress
// -------------------------------------------------------------------------
export {default as RadialProgress} from './ui/radial-progress/RadialProgress.svelte';

// -------------------------------------------------------------------------
// ui/radio-card
// -------------------------------------------------------------------------
export {default as RadioCard} from './ui/radio-card/RadioCard.svelte';
export {default as RadioCardGroup} from './ui/radio-card/RadioCardGroup.svelte';
export {
    createRadioCardContext,
    getRadioCardContext,
    RadioCardContext
} from './ui/radio-card/RadioCardContext.svelte.js';

// -------------------------------------------------------------------------
// ui/routing
// -------------------------------------------------------------------------
export {default as RouteError} from './ui/routing/RouteError.svelte';
export {default as RouteNotFound} from './ui/routing/RouteNotFound.svelte';
export {default as RouterView} from './ui/routing/RouterView.svelte';

// Routing curates its own public surface in `ui/routing/index.ts`, and that
// file is explicit about what it withholds and why. Re-export it wholesale
// rather than reaching into `logistics/`, `strategy/` or `hooks/` from here —
// a second, wider door into the same subsystem would defeat the point.
//
// `ui/routing/extendableTypes.js` is deliberately not re-exported: `declare
// module` augmentation has to name a declaration site directly, so consumers
// augment it through its own subpath.
export * from './ui/routing/index.js';

// -------------------------------------------------------------------------
// ui/select
// -------------------------------------------------------------------------
export {default as SingleSelect} from './ui/select/SingleSelect.svelte';
export type {ItemSnippetProps, SelectItemDefinition} from './ui/select/types.js';

// -------------------------------------------------------------------------
// ui/separator
// -------------------------------------------------------------------------
export {default as Separator} from './ui/separator/Separator.svelte';

// -------------------------------------------------------------------------
// ui/sheet
// -------------------------------------------------------------------------
export {default as BottomSheet} from './ui/sheet/BottomSheet.svelte';

// -------------------------------------------------------------------------
// ui/slider
// -------------------------------------------------------------------------
export {default as Slider} from './ui/slider/Slider.svelte';

// -------------------------------------------------------------------------
// ui/status-dot
// -------------------------------------------------------------------------
export {default as StatusDot} from './ui/status-dot/StatusDot.svelte';

// -------------------------------------------------------------------------
// ui/switch
// -------------------------------------------------------------------------
export {default as Switch} from './ui/switch/Switch.svelte';

// -------------------------------------------------------------------------
// ui/tabs
// -------------------------------------------------------------------------
export {default as Tabs} from './ui/tabs/Tabs.svelte';
export type {TabItem} from './ui/tabs/types.js';

// -------------------------------------------------------------------------
// ui/textarea
// -------------------------------------------------------------------------
export {default as Textarea} from './ui/textarea/Textarea.svelte';

// -------------------------------------------------------------------------
// ui/toast
// -------------------------------------------------------------------------
export {default as Toaster} from './ui/toast/Toaster.svelte';
export {
    createToastContext,
    ToastContext,
    useToastContext,
    type Toast,
    type ToastVariant
} from './ui/toast/ToastContext.svelte.js';

// -------------------------------------------------------------------------
// ui/tooltip
// -------------------------------------------------------------------------
export {default as Tooltip} from './ui/tooltip/Tooltip.svelte';
export {default as UrlPreviewTooltip} from './ui/tooltip/UrlPreviewTooltip.svelte';

// =========================================================================
// util
// =========================================================================

// -------------------------------------------------------------------------
// util/breakpoints
// -------------------------------------------------------------------------
export {default as Breakpoint} from './util/breakpoints/Breakpoint.svelte';
export {breakpointsQueries} from './util/breakpoints/breakpoints.js';
export type {
    BreakpointName,
    BreakpointExactProps,
    BreakpointRangeProps,
    BreakpointSnippetProps
} from './util/breakpoints/breakpoints.js';
export {useBreakpoint, type BreakpointState} from './util/breakpoints/useBreakpoint.svelte.js';

// -------------------------------------------------------------------------
// util/link
// -------------------------------------------------------------------------
export {default as Link} from './util/link/Link.svelte';
export {default as TextLink} from './util/link/TextLink.svelte';

// -------------------------------------------------------------------------
// util/markdown
// -------------------------------------------------------------------------
export {default as Markdown} from './util/markdown/Markdown.svelte';
export {default as ExtendedLinkNode} from './util/markdown/extension/ExtendedLinkNode.svelte';

// -------------------------------------------------------------------------
// util/snippetOrString
// -------------------------------------------------------------------------
export {default as SnippetOrString} from './util/snippetOrString/SnippetOrString.svelte';
export {default as SnippetOrStringTrigger} from './util/snippetOrString/SnippetOrStringTrigger.svelte';

// =========================================================================
// lib
// =========================================================================

// -------------------------------------------------------------------------
// lib/color-scheme
// -------------------------------------------------------------------------
export {
    provideColorScheme,
    useColorScheme,
    type ColorScheme,
    type ColorSchemeContextValue
} from './lib/color-scheme/ColorSchemeContext.js';

// -------------------------------------------------------------------------
// lib/i18n
// -------------------------------------------------------------------------
export {
    provideTranslator,
    useTranslator,
    type TranslationReplacements,
    type TranslatorInterface
} from './lib/i18n/TranslatorContext.js';

// -------------------------------------------------------------------------
// lib/link
// -------------------------------------------------------------------------
export {
    provideLinkServices,
    useLinkServices,
    type LinkPreviewMetadata,
    type LinkServices
} from './lib/link/LinkServicesContext.js';

// -------------------------------------------------------------------------
// lib/transitions
// -------------------------------------------------------------------------
export {growTransition} from './lib/transitions/growTransition.js';
