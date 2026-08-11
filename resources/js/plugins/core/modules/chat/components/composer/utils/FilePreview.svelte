<!--
  @component Small square preview `<img>` for a single staged/attached file,
  used inside `FileChips`. For images, shows an actual thumbnail — either the
  `RemoteFile.previewUrl` (already-uploaded files, e.g. reconstructed by
  `ChatEditMode`) or a fresh `URL.createObjectURL(file)` for a locally-picked
  `File` that hasn't been uploaded yet. For non-images, shows a generic
  file-type icon (via `getFileIconSvg`) keyed off the file extension.

  `src` is intentionally omitted from the forwarded HTML attributes (`Omit<...,
  'src'>`) since this component always computes it itself.

  @example
  ```svelte
  {#each composerContext.attachments.list as file}
      <FilePreview file={file}/>
  {/each}
  ```
-->
<script lang="ts">

    import {getFileIconSvg} from '$lib/utils/fileIconSvg.js';
    import type {HTMLAttributes} from 'svelte/elements';
    import {mergeProps} from 'bits-ui';
    import {RemoteFile} from '$plugins/core/modules/chat/components/composer/utils/RemoteFile.js';

    type Props = {
        /** The file to preview. May be a plain browser `File` (freshly picked/dropped,
         *  not yet uploaded) or a `RemoteFile` (already uploaded, previewed via its URL). */
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
