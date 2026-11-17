<!--
  @component Route-only page for the review detail group root (`/:assistantId`):
  its loader redirects to the first step, so the detail is always entered on a
  concrete step route. The component itself never renders (the redirect fires
  during data loading).
-->
<script module lang="ts">
    import {configurePage} from '$lib/components/ui/routing/index.js';

    export const config = configurePage({
        loadData: async (ctx) => ctx.redirect('admin.assistants.changes', ctx.params),
    });
</script>

<script lang="ts">
    interface Props {
    }

    const {}: Props = $props();
</script>
