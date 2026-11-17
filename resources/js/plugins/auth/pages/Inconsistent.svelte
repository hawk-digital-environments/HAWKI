<script lang="ts">
    import type { RouteProps } from '$lib/components/ui/routing/index.js';
    import { useTranslator } from '$lib/app/hooks/useTranslator.svelte.js';
    import ResetProfileButton from './ResetProfileButton.svelte';
    import AuthFrame from './AuthFrame.svelte';
    const {}: RouteProps = $props();
    const { __ } = useTranslator();
</script>
<AuthFrame>
    <div class="auth-intro">
        <h1 id="auth-title">{__('ui.auth.inconsistent.title')}</h1>
        <p class="auth-copy">{__('ui.auth.inconsistent.description')}</p>
    </div>
    <div class="auth-actions">
        <ResetProfileButton label={__('ui.auth.inconsistent.reset')}/>
    </div>
</AuthFrame>
