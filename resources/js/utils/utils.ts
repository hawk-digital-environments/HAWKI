/**
 * Shared TypeScript utility types for Svelte component props.
 *
 * `WithoutChild` / `WithoutChildren` / `WithoutChildrenOrChild` strip the `child`/`children`
 * snippet props from a bits-ui primitive's props type when you want to replace them with your
 * own slot or render the children yourself.
 *
 * `WithElementRef<T>` adds an optional `ref` binding so a parent can hold a reference to
 * the underlying DOM element:
 * ```svelte
 * <script lang="ts">
 *   import type {WithElementRef} from '$lib/utils/utils.js';
 *   interface Props extends WithElementRef<HTMLButtonAttributes, HTMLButtonElement> {}
 *   const {ref = $bindable(), ...rest}: Props = $props();
 * </script>
 * <button bind:this={ref} {...rest} />
 * ```
 */

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type WithoutChild<T> = T extends { child?: any } ? Omit<T, 'child'> : T;
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type WithoutChildren<T> = T extends { children?: any } ? Omit<T, 'children'> : T;
export type WithoutChildrenOrChild<T> = WithoutChildren<WithoutChild<T>>;
export type WithElementRef<T, U extends HTMLElement = HTMLElement> = T & { ref?: U | null };
