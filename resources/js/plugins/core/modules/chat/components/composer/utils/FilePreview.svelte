<script lang="ts">

    import {getFileIconSvg} from '$lib/utils/fileIconSvg.js';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {RemoteFile} from '$lib/components/chat/composer/utils/RemoteFile.js';

    type Props = {
        file: File;
    } & Omit<HTMLAttributes<HTMLImageElement>, 'src'>

    const {file, ...restProps}: Props = $props();

    const isImage = $derived.by(() => file.type.startsWith('image/'));
    const src = $derived.by(() => {
        if (isImage) {
            if (file instanceof RemoteFile) {
                return file.previewUrl;
            }
            return URL.createObjectURL(file);
        }
        return getFileIconSvg(file.name.split('.').pop() || '?');
    });
</script>
<img {...mergeProps({
    class: [
        'preview',
        isImage ? 'preview--image' : 'preview--icon'
    ],
    src
}, restProps)}
/>

<style>
    .preview {
        width: 2rem;
        height: 2rem;
        flex-shrink: 0;
        border-radius: var(--corner-xs);
        object-fit: cover;
    }

    .preview--icon {
        object-fit: contain;
    }
</style>
