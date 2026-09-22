<!--
  @component Profile settings: avatar upload, display name and bio, persisted
  through the `users` JSON:API resource. A picked avatar uploads right away;
  name and bio are saved together, once they differ from what was last saved.
-->
<script lang="ts">
    import z from 'zod';
    import Avatar from '$lib/components/ui/avatar/Avatar.svelte';
    import Button from '$lib/components/ui/button/Button.svelte';
    import Loader from '$lib/components/ui/loader/Loader.svelte';
    import Textarea from '$lib/components/ui/textarea/Textarea.svelte';
    import Tooltip from '$lib/components/ui/tooltip/Tooltip.svelte';
    import Camera01Icon from '$lib/components/ui/icons/iconset/Camera01Icon.svelte';
    import SettingsPage from '$lib/app/components/settings/SettingsPage.svelte';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useConnection} from '$lib/app/hooks/useConnection.svelte.js';
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
    const connection = useConnection();
    const restApi = useRestApi();
    const toast = useToastContext();
    const {__} = useTranslator();

    const uid = $props.id();
    const nameId = `${uid}-name`;
    const nameErrorId = `${uid}-name-error`;
    const bioId = `${uid}-bio`;
    const bioCountId = `${uid}-bio-count`;

    const info = $derived(connection.isAuthenticated ? connection.userinfo : undefined);

    // The form fields deliberately seed from the profile as it was on mount;
    // a later connection refresh must not clobber the user's unsaved edits.
    const initialInfo = connection.isAuthenticated ? connection.userinfo : undefined;
    let savedName = $state(initialInfo?.name ?? '');
    let savedBio = $state(initialInfo?.bio ?? '');
    let name = $state(initialInfo?.name ?? '');
    let bio = $state(initialInfo?.bio ?? '');
    let avatarIdentifier = $state(initialInfo?.avatar ?? null);
    let saving = $state(false);
    let uploading = $state(false);
    let fileInput = $state<HTMLInputElement | null>(null);
    let nameInput = $state<HTMLInputElement | null>(null);
    /** Inline validation message for the name field; null when valid. */
    let nameError = $state<string | null>(null);

    const dirty = $derived(name.trim() !== savedName.trim() || bio.trim() !== savedBio.trim());
    const canUploadAvatar = $derived(Boolean(config.storage_avatars));
    const displayName = $derived(name.trim() || info?.username || __('ui.profile.fallbackName'));
    const avatarUrl = $derived(app.uriBuilder.storageFileUri(avatarIdentifier) ?? undefined);

    async function save(event: SubmitEvent): Promise<void> {
        event.preventDefault();
        if (!info || !dirty || saving) return;

        const trimmedName = name.trim();
        if (!trimmedName) {
            nameError = __('ui.settings.profile.errorNameRequired');
            nameInput?.focus();
            return;
        }
        nameError = null;
        const trimmedBio = bio.trim();

        saving = true;
        try {
            await restApi.updateResource('users', String(info.id), {
                display_name: trimmedName,
                bio: trimmedBio
            });
            // Keep the cached connection in sync so freshly mounted components see the change.
            info.name = trimmedName;
            info.bio = trimmedBio;
            savedName = trimmedName;
            savedBio = trimmedBio;
            name = trimmedName;
            bio = trimmedBio;
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

<SettingsPage title={__('ui.settings.profile.title')}>
    <div class="identity">
        <Tooltip tooltip={__('ui.settings.profile.changeAvatar')} disabled={!canUploadAvatar}>
            {#snippet children({props})}
                <button
                    {...props}
                    type="button"
                    class="avatar-button"
                    disabled={!canUploadAvatar || uploading}
                    aria-label={__('ui.settings.profile.changeAvatar')}
                    onclick={() => fileInput?.click()}
                >
                    <span class="avatar-clip">
                        <Loader active={uploading} overlay label={__('ui.settings.profile.avatarUploading')}>
                            <Avatar src={avatarUrl} name={displayName} label={displayName} size={56}/>
                        </Loader>
                    </span>
                    {#if canUploadAvatar}
                        <span class="avatar-badge" aria-hidden="true">
                            <Camera01Icon size={13} strokeWidth={2}/>
                        </span>
                    {/if}
                </button>
            {/snippet}
        </Tooltip>
        <input
            bind:this={fileInput}
            type="file"
            accept={config.storage_avatars?.allowedMimeTypes.join(',') ?? 'image/*'}
            onchange={uploadAvatar}
            hidden
        />
        <div class="identity-text">
            <strong>{displayName}</strong>
            {#if info?.email}
                <span>{info.email}</span>
            {/if}
        </div>
    </div>

    <!-- novalidate: validation is reported inline (aria-invalid + message) instead of browser bubbles. -->
    <form onsubmit={save} novalidate>
        <div class="field-group">
            <label for={nameId}>{__('ui.settings.profile.nameLabel')}</label>
            <input
                id={nameId}
                class="field"
                bind:this={nameInput}
                bind:value={name}
                maxlength={NAME_MAX_LENGTH}
                autocomplete="name"
                required
                aria-invalid={nameError ? 'true' : undefined}
                aria-describedby={nameError ? nameErrorId : undefined}
                oninput={() => (nameError = null)}
            />
            {#if nameError}
                <p id={nameErrorId} class="field-error" role="alert">{nameError}</p>
            {/if}
        </div>

        <div class="field-group">
            <label for={bioId}>{__('ui.settings.profile.bioLabel')}</label>
            <Textarea
                id={bioId}
                bind:value={bio}
                maxlength={BIO_MAX_LENGTH}
                rows={4}
                style="resize: none"
                aria-describedby={bioCountId}
            />
            <small class="counter" id={bioCountId}>{bio.length}/{BIO_MAX_LENGTH}</small>
        </div>

        <div class="form-footer">
            <Button type="submit" size="xs" disabled={!dirty} aria-busy={saving}>
                {saving ? __('ui.settings.common.saving') : __('ui.settings.common.save')}
            </Button>
        </div>
    </form>
</SettingsPage>

<style>
    /* ── Identity ──────────────────────────────────────────────────────── */

    .identity {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }

    .avatar-button {
        position: relative;
        display: inline-flex;
        flex-shrink: 0;
        padding: 0;
        border: none;
        border-radius: var(--corner-full);
        background: none;
        cursor: pointer;
    }

    /* Without avatar storage the picture is only shown, not dimmed. */
    .avatar-button:disabled {
        cursor: default;
        opacity: 1;
    }

    .avatar-clip {
        display: flex;
        overflow: hidden;
        border-radius: var(--corner-full);
        /* The avatar sits in the loader as inline content; without a strut the
           line box hugs it exactly, so the clip stays a circle. */
        line-height: 0;
        transition: filter var(--duration-extra-fast) var(--easing-default);
    }

    .avatar-button:not(:disabled):hover .avatar-clip {
        filter: brightness(0.92);
    }

    .avatar-badge {
        position: absolute;
        right: calc(-1 * var(--space-0_5));
        bottom: calc(-1 * var(--space-0_5));
        display: grid;
        place-items: center;
        width: var(--space-6);
        height: var(--space-6);
        /* Cut out of the avatar by a ring in the dialog's own color. */
        border: 2px solid var(--color-surface-raised);
        border-radius: var(--corner-full);
        background: var(--color-interactive);
        color: var(--color-on-interactive);
    }

    .identity-text {
        display: flex;
        min-width: 0;
        flex-direction: column;
        gap: var(--space-0_5);
    }

    .identity-text strong,
    .identity-text span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .identity-text strong {
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-semibold);
    }

    .identity-text span {
        color: var(--color-text-muted);
        font-size: var(--font-size-xs);
    }

    /* ── Form ──────────────────────────────────────────────────────────── */

    form {
        /* Frames inside the dialog share one radius, concentric with its corners. */
        --textarea-radius: var(--corner-xs);

        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    .field-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-1_5);
    }

    label {
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-medium);
    }

    /* Mirrors the Textarea primitive so both fields read as one set. */
    .field {
        width: 100%;
        min-height: 2.5rem;
        padding: 0 var(--space-3);
        border: var(--border);
        border-radius: var(--corner-xs);
        background: transparent;
        box-shadow: var(--elevation-1);
        color: var(--color-text);
        font: inherit;
        font-size: var(--font-size-sm);
    }

    .field:focus-visible {
        border-color: var(--color-focus-ring);
        box-shadow: 0 0 0 2px var(--color-focus-ring);
        outline: none;
    }

    .field-error {
        color: var(--color-error);
        font-size: var(--font-size-xs);
    }

    .counter {
        align-self: flex-end;
        color: var(--color-text-muted);
        font-size: var(--font-size-xxs);
        font-variant-numeric: tabular-nums;
    }

    .form-footer {
        display: flex;
        justify-content: flex-end;
    }
</style>
