<script lang="ts">

    import Cancel01Icon from '$lib/components/ui/icons/iconset/Cancel01Icon.svelte';
    import {ActionIcon} from '$lib/components/ui/icons';
    import type {IconComponent} from '$lib/components/ui/icons';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte';

    const {__} = useTranslator();

    let {
        size = 'small',
        onClick,
        icon: Icon = Cancel01Icon,
        label,
    } = $props<{
        size?: "small" | "medium" | "large",
        onClick: () => void,
        icon?: IconComponent,
        label?: string,
    }>();

    const actionSize = $derived(size === 'medium' ? 'md' : size === 'large' ? 'lg' : 'xs');
</script>

<ActionIcon
        icon={Icon}
        label={label ?? __('ui.common.remove')}
        size={actionSize}
        onclick={() => onClick()}
/>
