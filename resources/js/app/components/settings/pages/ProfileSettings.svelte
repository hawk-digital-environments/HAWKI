<!--
  @component Profile settings: avatar upload, display name and bio, persisted
  through the `users` JSON:API resource.
-->
<script lang="ts">
    import z from 'zod';
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useAuthenticatedConnection} from '$lib/app/hooks/useConnection.svelte.js';
    import {useConfig} from '$lib/app/hooks/useConfig.svelte.js';
    import {useRestApi} from '$lib/app/hooks/useApi.js';
    import {useToastContext} from '$lib/components/ui/toast/ToastContext.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import type {RouteProps} from '$lib/components/ui/routing/index.js';

    const {}: RouteProps = $props();

    const NAME_MAX_LENGTH = 20;
    const BIO_MAX_LENGTH = 255;
    /** Matches the export size of the legacy avatar cropper. */
    const AVATAR_SIZE = 512;

    const app = useApp();
    const config = useConfig();
    const connectionBox = useAuthenticatedConnection();
    const connection = $derived(connectionBox.current);
    const restApi = useRestApi();
    const toast = useToastContext();
    const {__} = useTranslator();

    const info = $derived(connection?.userinfo);

    // The form fields deliberately seed from the profile as it was on mount;
    // a later connection refresh must not clobber the user's unsaved edits.
    const initialInfo = connectionBox.current?.userinfo;
    let name = $state(initialInfo?.name ?? '');
    let bio = $state(initialInfo?.bio ?? '');
    let avatarIdentifier = $state(initialInfo?.avatar ?? null);
    let saving = $state(false);
    let uploading = $state(false);
    let fileInput = $state<HTMLInputElement | null>(null);

    const avatarUrl = $derived(app.uriBuilder.storageFileUri(avatarIdentifier) ?? undefined);

    async function save(event: SubmitEvent): Promise<void> {
        event.preventDefault();
        if (!info) return;

        const trimmedName = name.trim();
        if (!trimmedName) {
            toast.error(__('ui.settings.profile.errorNameRequired'));
            return;
        }

        saving = true;
        try {
            await restApi.updateResource('users', String(info.id), {
                name: trimmedName,
                bio: bio.trim()
            });
            // Keep the cached connection in sync so freshly mounted components see the change.
            info.name = trimmedName;
            info.bio = bio.trim();
            toast.success(__('ui.settings.profile.saved'));
        } catch (error) {
            console.error('Failed to update the profile', error);
            toast.error(__('ui.settings.profile.saveError'));
        } finally {
            saving = false;
        }
    }

    /** Center-crops the image to a square and scales it to the avatar export size. */
    async function prepareAvatar(file: File): Promise<Blob> {
        const bitmap = await createImageBitmap(file);
        const side = Math.min(bitmap.width, bitmap.height);
        const target = Math.min(AVATAR_SIZE, side);
        const canvas = document.createElement('canvas');
        canvas.width = target;
        canvas.height = target;
        canvas.getContext('2d')!.drawImage(
            bitmap,
            (bitmap.width - side) / 2,
            (bitmap.height - side) / 2,
            side,
            side,
            0,
            0,
            target,
            target
        );
        bitmap.close();
        return await new Promise((resolve, reject) => canvas.toBlob(
            (blob) => blob ? resolve(blob) : reject(new Error('Failed to encode the avatar image')),
            'image/jpeg',
            0.9
        ));
    }

    async function uploadAvatar(event: Event): Promise<void> {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0];
        input.value = '';
        if (!file || !info) return;

        uploading = true;
        try {
            const form = new FormData();
            form.append('image', await prepareAvatar(file), 'avatar.jpg');
            const response = await restApi.postToResourceAction('users', 'actions/avatar', form, {
                schema: z.object({avatar: z.string().nullable(), url: z.string()})
            });
            avatarIdentifier = response.avatar;
            info.avatar = response.avatar;
            toast.success(__('ui.settings.profile.avatarSaved'));
        } catch (error) {
            console.error('Failed to upload the avatar', error);
            toast.error(__('ui.settings.profile.avatarError'));
        } finally {
            uploading = false;
        }
    }
</script>

<section class="settings-section">
    <header>
        <h2>{__('ui.settings.profile.title')}</h2>
        <p>{__('ui.settings.profile.description')}</p>
    </header>

    <div class="avatar-row">
        <Avatar
            src={avatarUrl}
            name={name || info?.username || ''}
            label={name || info?.username || __('ui.profile.fallbackName')}
            size={48}
        />
        <div>
            <strong>{name}</strong>
            <span>{__('ui.settings.profile.avatarHint')}</span>
        </div>
        <input
            bind:this={fileInput}
            type="file"
            accept={config.storage_avatars?.allowedMimeTypes.join(',') ?? 'image/*'}
            onchange={uploadAvatar}
            hidden
        />
        <Button
            type="button"
            variant="stroke"
            size="xs"
            disabled={uploading || !config.storage_avatars}
            onclick={() => fileInput?.click()}
        >
            {uploading ? __('ui.settings.profile.avatarUploading') : __('ui.settings.profile.changeAvatar')}
        </Button>
    </div>

    <form onsubmit={save}>
        <label>
            <span>{__('ui.settings.profile.nameLabel')}</span>
            <input bind:value={name} maxlength={NAME_MAX_LENGTH} autocomplete="name" required/>
        </label>
        <label>
            <span>{__('ui.settings.profile.bioLabel')}</span>
            <Textarea bind:value={bio} maxlength={BIO_MAX_LENGTH} rows={4}/>
            <small>{bio.length}/{BIO_MAX_LENGTH}</small>
        </label>

        <div class="form-footer">
            <Button type="submit" size="sm" disabled={saving}>
                {saving ? __('ui.settings.common.saving') : __('ui.settings.common.save')}
            </Button>
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
    small,
    .avatar-row span {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    small {
        font-size: var(--font-size-xxs);
        font-weight: var(--font-weight-normal);
        text-align: right;
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

    input:not([type='file']) {
        min-height: 2.5rem;
        padding: 0 var(--space-3);
        border: var(--border);
        border-radius: var(--corner-sm);
        background: var(--color-surface-light);
        color: var(--color-text);
        font: inherit;
        font-weight: var(--font-weight-normal);
    }

    input:not([type='file']):focus {
        border-color: var(--color-focus-ring);
        outline: none;
    }

    .form-footer {
        display: flex;
        justify-content: flex-end;
        padding-top: var(--space-1);
    }
</style>
