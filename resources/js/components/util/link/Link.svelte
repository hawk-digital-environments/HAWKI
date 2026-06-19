<script lang="ts">
    import type {HTMLAnchorAttributes, MouseEventHandler} from 'svelte/elements';
    import * as svelte from 'svelte';

    /**
     * Maybe I can document this?
     */
    interface Props extends HTMLAnchorAttributes {
        /**
         * The link to navigate to
         */
        href?: string;

        /**
         * The target of the link, defaults to an empty string -> browser default
         * @see https://github.com/sveltejs/sapper/issues/265 --> _self means ALWAYS reload for sveltekit!
         */
        target?: string;

        /**
         * The rel attribute of the link. Automatically set to "noopener noreferrer" when target is "_blank" (and no rel is provided) to prevent tabnabbing
         */
        rel?: string;

        /**
         * The onclick event handler
         */
        onclick?: MouseEventHandler<HTMLAnchorElement>;

        /**
         * The children to render inside the link
         */
        children?: svelte.Snippet;

        /**
         * Disables the link if set to true (both visually and functionally)
         */
        disabled?: boolean;
    }

    const {
        href: hrefRaw = '',
        target = '',
        rel: relRaw = '',
        onclick: onclickRaw,
        children,
        disabled,
        class: classNames,
        ...restProps
    }: Props = $props();

    const href = $derived.by(() => {
        if (!hrefRaw || disabled) {
            return 'javascript:void(0)';
        }
        return hrefRaw;
    });

    const rel = $derived.by(() => {
        if (relRaw) {
            return relRaw;
        }
        if (target === '_blank') {
            return 'noopener noreferrer';
        }
        return '';
    });

    const onclick = $derived.by(() => {
        if (disabled) {
            return (event: MouseEvent) => {
                event.preventDefault();
            };
        }
        return onclickRaw;
    })

    const dynamicProps = $derived.by(() => {
        const props: Record<string, any> = {};
        if (target) {
            props.target = target;
        }
        if (rel) {
            props.rel = rel;
        }
        if (onclick) {
            props.onclick = onclick;
        }
        return props;
    });
</script>

<a href="{href}"
   class={classNames}
   class:link={true}
   class:disabled={disabled}
   {...dynamicProps}
   {...restProps}>
    {@render children?.()}
</a>
