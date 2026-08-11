import type {Component} from 'svelte';
import type {HugeiconsProps} from '@hugeicons/svelte';

/**
 * Type alias for any icon component generated into `iconset/` (auto-generated
 * by vitePluginHugeicons — do not edit those files by hand). Use this as the
 * prop type whenever a component accepts an icon component as a prop, e.g.
 * `icon?: IconComponent = SomeDefaultIcon`, so callers can pass any icon from
 * `iconset/` interchangeably.
 */
export type IconComponent = Component<Omit<HugeiconsProps, 'icon'>>;
