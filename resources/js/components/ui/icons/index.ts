import type {Component} from 'svelte';
import type {HugeiconsProps} from '@hugeicons/svelte';

/** Pre-typed component alias for a Hugeicons icon. Use like `icon?: IconComponent`. */
export type IconComponent = Component<Omit<HugeiconsProps, 'icon'>>;
