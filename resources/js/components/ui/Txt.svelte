<script module lang="ts">
    type TagElementMap = {
        p: HTMLParagraphElement;
        span: HTMLSpanElement;
        div: HTMLDivElement;
        label: HTMLLabelElement;
        strong: HTMLElement;
        em: HTMLElement;
        h1: HTMLHeadingElement;
        h2: HTMLHeadingElement;
        h3: HTMLHeadingElement;
        h4: HTMLHeadingElement;
        h5: HTMLHeadingElement;
        h6: HTMLHeadingElement;
    };

    type Tag = keyof TagElementMap;
</script>
<!--
  @component
  Typography primitive. Renders any block or inline text element with
  design-token-backed size, weight and line-height props.
  Heading tags (h1–h6) carry sensible defaults; explicit props always win.
  Any additional HTML attribute (aria-*, id, data-*, …) is forwarded to the element.
-->
<script lang="ts" generics="T extends Tag = 'p'">
    import type {Snippet} from 'svelte';
    import type {HTMLAttributes} from 'svelte/elements';
    import {cva, type VariantProps} from 'class-variance-authority';
    import {mergeProps} from 'bits-ui';

    const txtVariants = cva('', {
        variants: {
            size: {
                xxs: 'size-xxs',
                xs: 'size-xs',
                sm: 'size-sm',
                base: 'size-base',
                lg: 'size-lg',
                xl: 'size-xl',
                '2xl': 'size-2xl'
            },
            weight: {
                normal: 'weight-normal',
                medium: 'weight-medium'
            },
            lineHeight: {
                tight: 'lh-tight',
                normal: 'lh-normal',
                loose: 'lh-loose'
            }
        },
        defaultVariants: {
            size: 'sm',
            weight: 'normal',
            lineHeight: 'normal'
        }
    });

    /** Per-tag typography defaults. Explicit props always override these. */
    const tagMap: Partial<Record<Tag, VariantProps<typeof txtVariants>>> = {
        h1: {size: '2xl', weight: 'medium', lineHeight: 'tight'},
        h2: {size: 'xl', weight: 'medium', lineHeight: 'tight'},
        h3: {size: 'lg', weight: 'medium', lineHeight: 'tight'},
        h4: {size: 'base', weight: 'medium', lineHeight: 'normal'},
        h5: {size: 'sm', weight: 'medium', lineHeight: 'normal'},
        h6: {size: 'sm', weight: 'medium', lineHeight: 'normal'}
    };

    interface Props extends HTMLAttributes<TagElementMap[T]> {
        /** HTML element to render. Defaults to `p`. */
        tag?: T;
        /** Font size — maps to `--font-size-{size}`. Defaults to `sm`. */
        size?: VariantProps<typeof txtVariants>['size'];
        /** Font weight — maps to `--font-weight-{weight}`. Defaults to `normal`. */
        weight?: VariantProps<typeof txtVariants>['weight'];
        /** Line height — maps to `--line-height-{lineHeight}`. Defaults to `normal`. */
        lineHeight?: VariantProps<typeof txtVariants>['lineHeight'];
        children: Snippet;
    }

    const {
        tag = 'p' as T,
        size,
        weight,
        lineHeight,
        children,
        ...restProps
    }: Props = $props();

    const elementProps: any = $derived(mergeProps(
        {
            class: txtVariants({
                size: size ?? tagMap[tag]?.size,
                weight: weight ?? tagMap[tag]?.weight,
                lineHeight: lineHeight ?? tagMap[tag]?.lineHeight
            })
        },
        {
            class: 'txt'
        },
        restProps
    ));
</script>

<svelte:element this={tag} {...elementProps}>
    {@render children()}
</svelte:element>

<style>
    .txt {
        display: inline-flex;
        position: relative;
        align-items: center;
        gap: var(--space-1);
    }

    /* ── Size ────────────────────────────────────────────────────────────── */
    .size-xxs {
        font-size: var(--font-size-xxs);
    }

    .size-xs {
        font-size: var(--font-size-xs);
    }

    .size-sm {
        font-size: var(--font-size-sm);
    }

    .size-base {
        font-size: var(--font-size-base);
    }

    .size-lg {
        font-size: var(--font-size-lg);
    }

    .size-xl {
        font-size: var(--font-size-xl);
    }

    .size-2xl {
        font-size: var(--font-size-2xl);
    }

    /* ── Weight ──────────────────────────────────────────────────────────── */
    .weight-normal {
        font-weight: var(--font-weight-normal);
    }

    .weight-medium {
        font-weight: var(--font-weight-medium);
    }

    /* ── Line height ─────────────────────────────────────────────────────── */
    .lh-tight {
        line-height: var(--line-height-tight);
    }

    .lh-normal {
        line-height: var(--line-height-normal);
    }

    .lh-loose {
        line-height: var(--line-height-loose);
    }
</style>
