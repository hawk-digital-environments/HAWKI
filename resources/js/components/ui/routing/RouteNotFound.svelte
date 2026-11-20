<!--
  @component Default 404 fallback rendered by `RouterView` when no route
  matches the current path. Pass a custom component via `RouterView`'s
  `notFoundComponent` prop to replace it.
-->
<script lang="ts">
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

    interface Props {
    }

    const {}: Props = $props();
    const {__} = useTranslator();
</script>

<h1>{__('ui.routing.notFoundTitle')}</h1>
<p>{__('ui.routing.notFoundDescription')}</p>
