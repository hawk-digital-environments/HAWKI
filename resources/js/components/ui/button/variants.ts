import {cva, type VariantProps} from 'class-variance-authority';

export const buttonVariants = cva('btn', {
    variants: {
        variant: {
            fill: 'btn--fill',
            accent: 'btn--accent',
            stroke: 'btn--stroke',
            ghost: 'btn--ghost',
            iconGhost: 'btn--iconGhost',
            delete: 'btn--delete'
        },
        size: {
            xs: 'btn--xs',
            sm: 'btn--sm',
            md: 'btn--md',
            // This is an internal size used when an icon is provided without children.
            // It can not be set directly via the `size` prop.
            iconOnly: 'btn--iconOnly'
        }
    },
    defaultVariants: {variant: 'fill', size: 'md'}
});

export type ButtonVariant = VariantProps<typeof buttonVariants>['variant'];
export type ButtonSize = Exclude<VariantProps<typeof buttonVariants>['size'], 'iconOnly'>;
