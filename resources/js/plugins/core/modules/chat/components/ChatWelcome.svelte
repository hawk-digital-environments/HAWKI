<!--
@component Centered empty-state hero shown while a chat has no messages yet.
Rendered by the new-chat page (`ChatIndex.svelte`) and by an opened
conversation without messages (`ChatConversation.svelte`). Reserves space for
the floating composer via the inherited `--composer-dock-height` property set
on the page element.
-->
<script lang="ts">
    import EyeTracker from '$lib/components/ui/eye-tracker/EyeTracker.svelte';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {useConnection} from '$lib/app/hooks/useConnection.svelte.js';

    const {__} = useTranslator();
    const connection = useConnection();
    const userName = $derived(connection.hasUserInfo ? connection.userinfo.name : '');
</script>

<div class="welcome">
    <span class="welcome-icon"><EyeTracker size={64} eyeScale={1.2} within=".welcome" /></span>
    <h1>{userName ? __('chat.page.welcomeTitle', {name: userName}) : __('chat.page.welcomeTitleAnonymous')}</h1>
    <p>{__('chat.page.welcomeDescription')}</p>
</div>

<style>
    .welcome {
        display: flex;
        height: 100%;
        min-height: 18rem;
        align-items: center;
        justify-content: center;
        padding: var(--space-6);
        padding-bottom: calc(var(--composer-dock-height, 0px) + var(--space-6));
        flex-direction: column;
        text-align: center;
    }

    .welcome-icon {
        margin-bottom: var(--space-4);
    }

    /* the tracker's 200px frame is sized for a wall of knobs; here it
       hugs the ball */
    .welcome-icon :global(.eyt) { width: auto; height: auto; }

    h1 { margin: 0 0 var(--space-2); font-size: var(--font-size-xl); font-weight: var(--font-weight-medium); }
    p { max-width: 34rem; margin: 0; color: var(--color-text-muted); }
</style>
