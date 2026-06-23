//#region UPLOAD FILE

const uploadQueues = new Map();


// Add file to the UI for display

function createAttachmentThumbnail(fileData, thumbType) {

    const attachTemp = document.getElementById('attachment-thumbnail-template');
    const attachClone = attachTemp.content.cloneNode(true);
    const attachment = attachClone.querySelector('.attachment');
    attachment.dataset.fileId = fileData.uuid ?? fileData.tempId;
    attachment.dataset.mime = fileData.mime;
    attachment.querySelector('.name-tag').innerText = fileData.name;

    const iconImg = attachment.querySelector('img');
    let imgPreview = '';
    const type = checkFileFormat(fileData.mime);
    switch (type) {
        case('image'):
            if (fileData.url) {
                imgPreview = fileData.url;
            }
            if (fileData.file) {
                imgPreview = URL.createObjectURL(fileData.file);
            }

            attachment.querySelector('.attachment-icon').classList.add('boarder');
            break;
        default:
            imgPreview = window.getFileIconSvg(fileData.name.split('.').pop());
            break;
    }

    iconImg.setAttribute('src', imgPreview);
    return attachment;
}

async function openAttachmentDropDown(burgerBtn, attachment, fileData) {
    const burgerMenu = document.querySelector('#attachment-menu');

    const openBtn = burgerMenu.querySelector('#open-btn');
    const downloadBtn = burgerMenu.querySelector('#download-btn');
    const removeBtn = burgerMenu.querySelector('#remove-btn');

    // Documents cannot be previewed, so hide the open button for them
    if (checkFileFormat(fileData.mime) === 'document') {
        openBtn.style.display = 'none';
        openBtn.disabled = true;
    } else {
        openBtn.style.display = '';
        openBtn.disabled = false;
    }

    // Define handlers
    async function openHandler() {
        updateFileStatus(fileData.uuid, 'uploading');
        if (activeModule === 'chat') {
            await previewFile(attachment, fileData, 'conv');
        }
        if (activeModule === 'groupchat') {
            await previewFile(attachment, fileData, 'room');
        }
        updateFileStatus(fileData.uuid, 'finished');

    }

    async function downloadHandler() {
        if (activeModule === 'chat') {
            await downloadFile(fileData.uuid, 'conv', fileData.name);
        }
        if (activeModule === 'groupchat') {
            await downloadFile(fileData.uuid, 'room', fileData.name);
        }
    }

    function removeHandler() {
        onDeleteClicked(fileData, attachment);
    }

    // First remove old ones
    openBtn.removeEventListener('click', openBtn._handler);
    downloadBtn.removeEventListener('click', downloadBtn._handler);
    removeBtn.removeEventListener('click', removeBtn._handler);

    // Then assign and store the new ones
    openBtn.addEventListener('click', openHandler);
    openBtn._handler = openHandler;

    downloadBtn.addEventListener('click', downloadHandler);
    downloadBtn._handler = downloadHandler;

    removeBtn.addEventListener('click', removeHandler);
    removeBtn._handler = removeHandler;

    openBurgerMenu('attachment-menu', burgerBtn, true, false, true);
}

async function onDeleteClicked(fileData, attachment) {
    const confirmed = await openModal(ModalType.WARNING, __('Cnf_deleteFile'));
    if (!confirmed) {
        return;
    }
    let success;
    if (activeModule === 'chat') {
        success = requestAtchDelete(fileData.uuid, 'conv');
    }
    if (activeModule === 'groupchat') {
        success = requestAtchDelete(fileData.uuid, 'room');
    }
    if (success) {
        attachment.remove();
    }
}

async function requestAtchDelete(fileId, category) {
    const url = `/req/${category}/attachment/delete`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                'fileId': fileId
            })
        });
        const data = await response.json();
        if (data.success) {
            window.oldUiMessageHistory.removeFileByUuid(fileId);
            return true;
        } else {
            console.error('Failed to remove attachment: ' + data.err);
            return false;
        }
    } catch (err) {
        return false;
    }
}


// Update file status in UI
function updateFileStatus(fileId, status) {

    const fileElement = document.querySelector(`.attachment[data-file-id="${fileId}"]`);

    if (!fileElement) return;

    // Remove existing status classes
    fileElement.classList.remove('status-pending', 'status-uploading', 'status-complete', 'status-error', 'status-finished');
    fileElement.classList.add(`status-${status}`);

    // Update any status indicators in the UI
    const statusIndicator = fileElement.querySelector('.status-indicator');
    const stats = statusIndicator.querySelectorAll('.status');
    stats.forEach(stat => {
        stat.style.visibility = 'hidden';
    });

    if (status === 'finished') {
        return;
    }

    if (statusIndicator) {
        switch (status) {
            case 'uploading':
                statusIndicator.querySelector('#upload-stat').style.visibility = 'visible';
                break;
            case 'complete':
                statusIndicator.querySelector('#complete-stat').style.visibility = 'visible';
                break;
            case 'error':
                statusIndicator.querySelector('#error-stat').style.visibility = 'visible';
                break;

        }
    }
}


/**
 * Upload all attachments from the queue to the server.
 *
 * @param {SendMessageStatus} status - The current status of the chat, used for determining upload context.
 * @param {File[]} attachments - The list of files to be uploaded.
 * @param {string} category - The category/type of upload.
 * @param {string} slug
 * @returns {Promise<array|null>} - List of uploaded file metadata or null.
 */
async function uploadAttachmentQueue(status, attachments, category, slug = '') {
    let url = '';
    if (slug) {
        url = `/req/${category}/attachment/upload/${slug}`;
    } else {
        url = `/req/${category}/attachment/upload`;
    }

    const uploadedFiles = [];

    const uploadTasks = attachments.map(attachment => {

        const upload = uploadFileToServer(status, attachment, url);

        return upload.promise
            .then(data => {
                status.setFileUuid(attachment, data.uuid);
                uploadedFiles.push(data.uuid);
            })
            .catch(error => {
                console.error(`Upload failed for ${attachment.name}:`, error);
            });
    });

    await Promise.all(uploadTasks);
    return uploadedFiles;
}
