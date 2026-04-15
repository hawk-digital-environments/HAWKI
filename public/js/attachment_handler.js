//#region UPLOAD FILE
const uploadQueues = new Map();

// Trigger click on the file input element
function selectFile(sender) {
    const inputContainer = sender.closest(".input-container");
    const fileInput = inputContainer.querySelector('.file-upload-input');
    if (fileInput) {
        fileInput.click();
    }
    else{
        console.error("Could not get fileinput");
    }
}

function initFileUploader(inputField) {

    const overlay = inputField.querySelector('.drag-drop-overlay');
    const fileInput = inputField.querySelector('.file-upload-input');
    const input = inputField.querySelector('.input');
    const ctrls = inputField.querySelector('.input-controls');
    let dragCounter = 0;

    const allowedMimeTypes = hawkiConnection('storage.allowedMimeTypes');

    // Set the allowed mime types on the file-upload-input input field, so the file picker also filters unsupported types
    if (fileInput) {
        fileInput.setAttribute('accept', allowedMimeTypes.join(','));
    }

    // Builds a single status sentence shown instead of the limits/desc during drag.
    // Collects unique unsupported MIME types; uses singular/plural translation keys accordingly.
    function updateDragFileList(fileInfos) {
        const msgEl = overlay && overlay.querySelector('.drag-status-msg');
        if (!msgEl) return;

        const invalidTypes = [...new Set(
            fileInfos.filter(f => !f.allowed).map(f => f.type || '?')
        )].map(type => {
            // At max 30 chars to prevent overflow, (cut from beginning, so we have the ending always visible) prefix with...
            // Wrap with quotes to make it clearer, especially for empty MIME types
            return type.length > 30 ? `"...${type.slice(-30)}"` : `"${type}"`;
        });

        let message;
        if (invalidTypes.length === 0) {
            message = __('Upload_Overlay_Valid_Msg');
        } else if (invalidTypes.length === 1) {
            message = __('Upload_Overlay_Invalid_Single', {type: invalidTypes[0]});
        } else {
            message = __('Upload_Overlay_Invalid_Multiple', {types: invalidTypes.join(', ')});
        }

        msgEl.textContent = message;
        msgEl.style.display = 'block';
    }

    function clearDragFileList() {
        const msgEl = overlay && overlay.querySelector('.drag-status-msg');
        if (msgEl) {
            msgEl.textContent = '';
            msgEl.style.display = 'none';
        }
    }

    // Drag and drop handling
    inputField.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    inputField.addEventListener('dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter++;
        ctrls.classList.add('minimized');
        overlay.style.display = 'flex';

        // Detect dragged file MIME types and show validity feedback
        const items = e.dataTransfer && e.dataTransfer.items;
        if (items && items.length > 0) {
            let allAllowed = true;
            const fileInfos = [];
            for (let i = 0; i < items.length; i++) {
                if (items[i].kind === 'file') {
                    const mimeType = items[i].type || 'unknown/unknown';
                    const allowed = !mimeType || allowedMimeTypes.includes(mimeType);
                    if (!allowed) allAllowed = false;
                    fileInfos.push({type: mimeType, allowed});
                }
            }
            if (fileInfos.length > 0) {
                overlay.classList.toggle('drag-valid', allAllowed);
                overlay.classList.toggle('drag-invalid', !allAllowed);
                updateDragFileList(fileInfos);
            }
        }
    });

    inputField.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter--;
        if (dragCounter === 0) {
            ctrls.classList.remove('minimized');
            overlay.style.display = 'none';
            overlay.classList.remove('drag-valid', 'drag-invalid');
            clearDragFileList();
        }
    });

    inputField.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter = 0;
        overlay.style.display = 'none';
        overlay.classList.remove('drag-valid', 'drag-invalid');
        clearDragFileList();
        handleSelectedFiles(e.dataTransfer.files, input);

    });

    // File input button handling
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            handleSelectedFiles(this.files, input);
            this.value = '';
        });
    }

}


// Handle files from drag-drop or file picker
async function handleSelectedFiles(files, inputField) {
    const input_id = inputField.id;
    const attachmentContainer = inputField.querySelector('.file-attachments');

    if (!files || files.length === 0) return;

    const allowedTypes = hawkiConnection('storage.allowedMimeTypes');
    const maxFileSize = hawkiConnection('storage.maxFileSize');
    const maxFileSizeMB = (maxFileSize / (1024 * 1024)).toFixed(2);
    const maxFileSizeReadable = `${maxFileSizeMB} MB`;

    // Convert FileList to Array and process all files in parallel
    Array.from(files).map(async file => {
        // File type validation
        if (!allowedTypes.includes(file.type)) {
            showFeedbackMsg(inputField, 'error', `${__('Input_Err_NotSupported')} ${file.type || 'unknown/unknown'}`);
            return null; // Early exit from this file's processing
        }
        queueAnchoredAnnouncements('FileUpload');


        // File size validation
        if (file.size > maxFileSize) {
            showFeedbackMsg(inputField, 'error', `${__('Input_Err_MaxSize')} ${maxFileSizeReadable}`);
            return null;
        }

        if(!checkFilterCombination(input_id, getFilterFromMime(file.type))){
            showFeedbackMsg(inputField, 'error', `${__('Input_Err_FilterConflict')}`);
            return;
        }

        // Prepare file for upload
        const fileData = createFileStruct(file);
        const atchThumb = createAttachmentThumbnail(fileData, 'input');

        // Add to file preview container
        if(!attachmentContainer.classList.contains('active')){
            attachmentContainer.classList.add('active');
        }

        //create a file queue
        if (!uploadQueues.has(input_id)) {
            uploadQueues.set(input_id, []);
        }
        attachmentContainer.querySelector('.attachments-list').appendChild(atchThumb);

        uploadQueues.get(input_id).push({ fileData });

        setAttachmentsFilter(input_id);

    });
}

function setAttachmentsFilter(input_id){
    const attachments = uploadQueues.get(input_id);

    let fileUploadFilterFlag = false;
    let visionFilterFlag = false;
    attachments.forEach(attachment => {
        const type = checkFileFormat(attachment.fileData.mime);
        if(type === 'image'){
            visionFilterFlag = true;
            addInputFilter(input_id, 'vision');
        } else {
            fileUploadFilterFlag = true;
            addInputFilter(input_id, 'file_upload');
        }
    });

    if(!visionFilterFlag){
        removeInputFilter(input_id, 'vision');
    }
    if(!fileUploadFilterFlag){
        removeInputFilter(input_id, 'file_upload');
    }
}

// Prepare file for upload by creating needed metadata
function createFileStruct(file) {
    return {
        tempId: generateUniqueId(),
        file: file,
        name: file.name,
        size: file.size,
        mime: file.type,
        lastModified: file.lastModified,
        status: 'pending' // pending, uploading, complete, error
    };
}



// Add file to the UI for display

function createAttachmentThumbnail(fileData, thumbType) {

    const attachTemp = document.getElementById('attachment-thumbnail-template')
    const attachClone = attachTemp.content.cloneNode(true);
    const attachment = attachClone.querySelector(".attachment");
    attachment.dataset.fileId = fileData.uuid ?? fileData.tempId;
    attachment.dataset.mime = fileData.mime;
    attachment.querySelector('.name-tag').innerText = fileData.name;

    const iconImg = attachment.querySelector('img');
    let imgPreview = '';
    const type = checkFileFormat(fileData.mime);
    switch(type){
        case('image'):
        if(fileData.url){
            imgPreview = fileData.url;
        }
        if (fileData.file) {
            imgPreview = URL.createObjectURL(fileData.file);
        }

        attachment.querySelector('.attachment-icon').classList.add('boarder');
        break;
        default:
            imgPreview = getFileIconSvg(fileData.name.split('.').pop());
            break;
    }


    if(thumbType === 'message'){
        attachment.querySelector('.controls').remove();
        const burgerBtn = attachment.querySelector('.burger-btn')
        burgerBtn.addEventListener('click', ()=> {
            openAttachmentDropDown(burgerBtn, attachment, fileData);
        })

    }
    if(thumbType === 'input'){
        attachment.querySelector('.burger-btn').remove();

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

    openBurgerMenu("attachment-menu", burgerBtn, true, false, true);
}

async function onDeleteClicked(fileData, attachment){
    const confirmed = await openModal(ModalType.WARNING, __('Cnf_deleteFile'));
    if (!confirmed) {
        return;
    }
    let success;
    if(activeModule === 'chat'){
        success = requestAtchDelete(fileData.uuid, 'conv');
    }
    if(activeModule === 'groupchat'){
        success = requestAtchDelete(fileData.uuid, 'room');
    }
    if(success){
        attachment.remove();
    }
}

// Remove file attachment from UI and storage
function removeAtchFromInputList(providerBtn) {
    const input = providerBtn.closest('.input');
    const fileId = providerBtn.closest('.attachment').dataset.fileId;

    removeAtchFromList(fileId, input.id);
    setAttachmentsFilter(input.id);
}

function removeAtchFromList(fileId, queueId){
    // Remove from UI
    const fileElement = document.querySelector(`.attachment[data-file-id="${fileId}"]`);

    if (fileElement) {
        fileElement.remove();
    }

    // Remove from pending uploads array
    const queue = uploadQueues.get(queueId);

    if (queue) {
        const index = queue.findIndex(item => item.fileData.tempId === fileId);
        if (index !== -1) {
            queue.splice(index, 1);
        }
    }
    setAttachmentsFilter(queueId);

    // If no more attachments, remove container
    const input = document.querySelector(`.input[id="${queueId}"`);
    const list = input.querySelector('.attachments-list');
    if (list && list.children.length === 0) {
        list.closest('.file-attachments').classList.remove('active');
    }
}


async function requestAtchDelete(fileId, category){
    const url = `/req/${category}/attachment/delete`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try{
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                'fileId': fileId,
            })
        });
        const data = await response.json();
        if(data.success){
            return true;
        }
        else{
            console.error('Failed to remove attachment: ' + data.err);
            return false;
        }
    }
    catch(err){
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
        stat.style.visibility = "hidden";
    });

    if(status ==='finished'){
        return;
    }

    if (statusIndicator) {
        switch (status) {
            case 'uploading':
                statusIndicator.querySelector('#upload-stat').style.visibility = 'visible'
                break;
            case 'complete':
                statusIndicator.querySelector('#complete-stat').style.visibility = 'visible'
                break;
            case 'error':
                statusIndicator.querySelector('#error-stat').style.visibility = 'visible'
                break;

        }
    }
}



/**
 * Upload all attachments from the queue to the server.
 *
 * @param {string} queueId - The ID of the upload queue.
 * @param {string} category - The category/type of upload.
 * @param {string} slug
 * @returns {Promise<array|null>} - List of uploaded file metadata or null.
 */
async function uploadAttachmentQueue(queueId, category, slug = '') {
    let url = '';
    if(slug){
        url = `/req/${category}/attachment/upload/${slug}`;
    }
    else{
        url = `/req/${category}/attachment/upload`;
    }
    const attachments = uploadQueues.get(queueId);

    if (!attachments || attachments.length === 0) return null;

    const uploadedFiles = [];

    const uploadTasks = attachments.map(attachment => {
        updateFileStatus(attachment.fileData.tempId, 'uploading');

        const upload = uploadFileToServer(attachment.fileData, url, (tempId, status, percent, fileUrl = null) => {
            updateFileStatus(attachment.fileData.tempId, status, fileUrl);
            if(status === 'error'){
                const inputField = document.querySelector(`.input[id=${queueId}`);
                showFeedbackMsg(inputField, 'error', __('Input_Err_UploadFailed'));
            }
        });

        const removeBtn = document.querySelector(`.attachment[data-file-id="${attachment.fileData.tempId}"]`).querySelector('.remove-btn');
        removeBtn.addEventListener('click', () => {
            upload.abort();
        });

        return upload.promise
            .then(data => {
                attachment.fileData.uuid = data.uuid;
                uploadedFiles.push(data.uuid);
                updateFileStatus(attachment.fileData.tempId, 'complete');
                removeAtchFromList(attachment.fileData.tempId, queueId);
            })
            .catch(error => {
                console.error(`Upload failed for ${attachment.fileData.name}:`, error);
                updateFileStatus(attachment.fileData.tempId, 'error');
                // Optionally handle failed uploads
            });
    });

    await Promise.all(uploadTasks);
    return uploadedFiles;
}
