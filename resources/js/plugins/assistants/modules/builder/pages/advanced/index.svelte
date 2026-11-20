<!--
  @component Route-only page for the builder group root (`/advanced`): its
  loader redirects to the first builder section, so the builder is always
  entered on a concrete section route — the sidebar's drill-down level and
  its section rows key off those. The component itself never renders (the
  redirect fires during data loading, before anything mounts); it exists
  because a route needs one, and the loader needs a module to live in.

  This is the documented loader-redirect pattern (see the routing docs on
  `ctx.redirect`): an unconditional per-route redirect is a property of the
  page, not a guard on external state — that distinction belongs to
  middlewares.
-->
<script module lang="ts">
    import {configurePage} from '$lib/components/ui/routing/index.js';

    export const config = configurePage({
        loadData: async (ctx) => ctx.redirect('assistants.builder.general'),
    });
</script>

<script lang="ts">
    /**
     * The kernel's route renderer instantiates page components without
     * passing any props (see the section pages), so this interface is
     * intentionally empty — the component never renders anyway.
     */
    interface Props {
    }

    const {}: Props = $props();
</script>

