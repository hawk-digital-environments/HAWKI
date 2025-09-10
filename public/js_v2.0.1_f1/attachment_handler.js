//#region UPLOAD FILE
const uploadQueues = new Map();

// Trigger click on the file input element
function selectFile() {
    document.querySelector('#file-upload-input').click();
}


function initFileUploader(inputField) {

    const overlay = inputField.querySelector('.drag-drop-overlay');
    const fileInput = document.querySelector('#file-upload-input');
    const input = inputField.querySelector('.input');
    const ctrls = inputField.querySelector('.input-controls');
    let dragCounter = 0;

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
    });

    inputField.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter--;
        // Only hide if all drags have left this element
        if (dragCounter === 0) {
            ctrls.classList.remove('minimized');
            overlay.style.display = 'none';
        }
    });

    inputField.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter = 0;
        overlay.style.display = 'none';
        handleSelectedFiles(e.dataTransfer.files, input);
        queueAnchoredAnnouncements('FileUpload');

    });

    // File input button handling
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            handleSelectedFiles(this.files, input);
        });
    }

}


// Handle files from drag-drop or file picker
async function handleSelectedFiles(files, inputField) {
    const input_id = inputField.id;
    const attachmentContainer = inputField.querySelector('.file-attachments');

    if (!files || files.length === 0) return;

    const allowedTypes = [
        // Images
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    ];
    if(converterActive){
        allowedTypes.push(
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
    }


    const maxMB = 10;
    const maxFileSize = maxMB * 1024 * 1024; // 10MB limit

    // Convert FileList to Array and process all files in parallel
    Array.from(files).map(async file => {
        // File type validation
        if (!allowedTypes.includes(file.type)) {
            showFeedbackMsg(inputField, 'error', `${translation.Input_Err_NotSupported} ${file.type}`);
            return null; // Early exit from this file's processing
        }

        // File size validation
        if (file.size > maxFileSize) {
            showFeedbackMsg(inputField, 'error',  `${translation.Input_Err_MaxSize} ${maxMB}`);
            return null;
        }

        if(!checkFilterCombination(input_id, getFilterFromMime(file.type))){
            showFeedbackMsg(inputField, 'error', `${translation.Input_Err_FilterConflict}`)
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
        if(type === 'pdf' || type === 'docx' || type === 'image'){
            fileUploadFilterFlag = true;
            addInputFilter(input_id, 'file_upload');
        }
        if(type === 'image'){
            visionFilterFlag = true;
            addInputFilter(input_id, 'vision');
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
        case('pdf'):
            imgPreview = '/img/fileformat/pdf.png';
        break;
        case('docx'):
            imgPreview = '/img/fileformat/doc.png';
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
    const confirmed = await openModal(ModalType.WARNING , translation.Cnf_deleteFile);
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
    console.log(fileId);
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
 * @returns {Promise<array|null>} - List of uploaded file metadata or null.
 */
async function uploadAttachmentQueue(queueId, category, slug = null) {
    console.log('uploading FIle ', queueId, category, slug);
    let url = '';
    if(slug){
        url = `/req/${category}/attachment/upload/${slug}`;
    }
    else{
        url = `/req/${category}/attachment/upload`;
    }
    const attachments = uploadQueues.get(queueId);
    console.log('queue');
    console.log(attachments);

    if (!attachments || attachments.length === 0) return null;

    const uploadedFiles = [];

    const uploadTasks = attachments.map(attachment => {
        updateFileStatus(attachment.fileData.tempId, 'uploading');

        const upload = uploadFileToServer(attachment.fileData, url, (tempId, status, percent, fileUrl = null) => {
            updateFileStatus(attachment.fileData.tempId, status, fileUrl);
            if(status === 'error'){
                const inputField = document.querySelector(`.input[id=${queueId}`);
                showFeedbackMsg(inputField, 'error', translation.Input_Err_UploadFailed);
            }
        });

        const removeBtn = document.querySelector(`.attachment[data-file-id="${attachment.fileData.tempId}"]`).querySelector('.remove-btn');
        removeBtn.addEventListener('click', () => {
            upload.abort();
        });

        return upload.promise
            .then(data => {
                console.log(data)
                attachment.fileData.uuid = data.uuid;
                uploadedFiles.push({
                    uuid: data.uuid,
                    name: attachment.fileData.name,
                    mime: attachment.fileData.mime,
                });
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
