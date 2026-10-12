<!--
  @component The deployment's logo: the SVG an administrator uploaded
  (`branding.logoUrl`), otherwise the HAWKI wordmark. Labelled with the
  application name unless `label` says more.
-->
<script lang="ts">
    import type {HTMLAttributes} from 'svelte/elements';
    import HawkLogo from '$lib/components/ui/logo/HawkLogo.svelte';
    import {useConfig} from '$lib/app/hooks/useConfig.svelte.js';

    interface Props extends HTMLAttributes<HTMLSpanElement> {
        /** Accessible label; defaults to the application name. */
        label?: string;
    }

    const {label, ...restProps}: Props = $props();
    // Read through the config on every render: the admin panel swaps the logo while it is being edited.
    const config = useConfig();
</script>

<HawkLogo {...restProps} src={config.branding?.logoUrl} label={label ?? config.branding?.appName}/>
