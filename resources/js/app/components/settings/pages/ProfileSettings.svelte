<script lang="ts">
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useConnectionWithUserInfo} from '$lib/app/hooks/useConnection.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {RouteComponentProps} from '$lib/components/ui/routing/logistics/RouteRegistrar.js';

    const {}: RouteComponentProps = $props();

    const app = useApp();
    const connection = useConnectionWithUserInfo();
    const {__} = useTranslator();
    const info = connection?.userinfo;
    const avatarIdentifier = info && 'avatar' in info && typeof info.avatar === 'string' ? info.avatar : null;

    let name = $state(info?.name ?? __('ui.profile.mockName'));
    let email = $state(info?.email ?? 'alex@example.com');
    let username = $state(info?.username ?? 'alex');
    let saved = $state(false);

    function saveMock(event: SubmitEvent): void {
        event.preventDefault();
        saved = true;
    }
</script>

<section class="settings-section">
    <header>
        <h2>{__('ui.profile.profileSettings.title')}</h2>
        <p>{__('ui.profile.profileSettings.description')}</p>
    </header>

    <div class="avatar-row">
        <Avatar
            src={app.uriBuilder.storageFileUri(avatarIdentifier) ?? undefined}
            name={name}
            size={48}
        />
        <div>
            <strong>{name}</strong>
            <span>{__('ui.profile.profileSettings.avatarHint')}</span>
        </div>
        <Button type="button" variant="stroke" size="xs" disabled>
            {__('ui.profile.profileSettings.changeAvatar')}
        </Button>
    </div>

    <form onsubmit={saveMock} oninput={() => (saved = false)}>
        <label>
            <span>{__('ui.profile.profileSettings.nameLabel')}</span>
            <input bind:value={name} autocomplete="name"/>
        </label>
        <label>
            <span>{__('ui.profile.profileSettings.usernameLabel')}</span>
            <input bind:value={username} autocomplete="username"/>
        </label>
        <label>
            <span>{__('ui.profile.profileSettings.emailLabel')}</span>
            <input bind:value={email} type="email" autocomplete="email"/>
        </label>

        <div class="form-footer">
            <span class:saved>{saved ? __('ui.profile.mockSaved') : __('ui.profile.mockNotice')}</span>
            <Button type="submit" size="sm">{__('ui.profile.saveChanges')}</Button>
        </div>
    </form>
</section>

<style>
    .settings-section {
        display: flex;
        flex-direction: column;
        gap: var(--space-5);
        max-width: 28rem;
    }

    header,
    label,
    .avatar-row > div {
        display: flex;
        flex-direction: column;
    }

    header {
        gap: var(--space-1);
    }

    h2,
    p {
        margin: 0;
    }

    h2 {
        font-size: var(--font-size-md);
    }

    p,
    .avatar-row span,
    .form-footer > span {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    .avatar-row {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        padding-bottom: var(--space-5);
        border-bottom: var(--divider);
    }

    .avatar-row > div {
        min-width: 0;
        flex: 1;
        gap: var(--space-0_5);
    }

    .avatar-row strong,
    .avatar-row span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    label {
        gap: var(--space-1_5);
        color: var(--color-text);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
    }

    input {
        min-height: 2.5rem;
        padding: 0 var(--space-3);
        border: var(--border);
        border-radius: var(--corner-sm);
        background: var(--color-surface-light);
        color: var(--color-text);
        font: inherit;
        font-weight: var(--font-weight-normal);
    }

    input:focus {
        border-color: var(--color-focus-ring);
        outline: none;
    }

    .form-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-3);
        padding-top: var(--space-1);
    }

    .form-footer > span.saved {
        color: var(--color-success);
    }
</style>
