<!--
  @component Route-only page for the assistants plugin prefix
  (`/assistants`): its loader redirects to the dashboard module (the
  plugin's canonical landing page — the store), so the bare plugin URL is
  always entered on a real page. The component itself never renders (the
  redirect fires during data loading, before anything mounts); it exists
  because a route needs one, and the loader needs a module to live in.

  This is the documented loader-redirect pattern (see the routing docs on
  `ctx.redirect`): an unconditional per-route redirect is a property of the
  page, not a guard on external state — that distinction belongs to
  middlewares. `redirect()` replaces the history entry by default, so back
  from the dashboard skips over the bare prefix instead of re-triggering
  the redirect.
-->
<script module lang="ts">
    import {configurePage} from '$lib/components/ui/routing/index.js';

    export const config = configurePage({
        loadData: async (ctx) => ctx.redirect('assistants.dashboard.index'),
    });
</script>

<script lang="ts">
    /**
     * The kernel's route renderer instantiates page components without
     * passing any props (see the builder's section pages), so this interface
     * is intentionally empty — the component never renders anyway.
     */
    interface Props {
    }

    const {}: Props = $props();
</script>
