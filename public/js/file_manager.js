/**
 * Upload a file with progress tracking and cancel support.
 *
 * @param {SendMessageStatus} status - The current chat status object.
 * @param {File} file - The file to be uploaded.
 * @param {string} url - The server upload URL.
 * @returns {{ promise: Promise<object>, abort: () => void }}
 */
function uploadFileToServer(status, file, url) {
    let xhr = new XMLHttpRequest();

    const promise = new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('file', file);

        // Initial progress state
        status.setFileProgress(file, 0);
        xhr.open('POST', url, true);

        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken.getAttribute('content'));
        }

        // Upload progress tracking
        xhr.upload.onprogress = (event) => {
            status.setFileProgress(file, Math.round((event.loaded / event.total) * 100));
        };

        // Upload success
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const responseData = JSON.parse(xhr.responseText);
                    status.setFileProgress(file, 100);
                    if (responseData.uuid) {
                        status.setFileUuid(file, responseData.uuid);
                    } else {
                        throw new Error('Missing UUID in server response');
                    }
                    resolve(responseData);
                } catch (e) {
                    status.addFileIssue(file, window.__('legacy.fileManager.invalidServerResponse'));
                    reject('Invalid server response');
                }
            } else {
                status.addFileIssue(file, window.__('legacy.fileManager.invalidServerResponse'));
                reject(`Upload failed: ${xhr.statusText}`);
            }
        };

        // Network error
        xhr.onerror = () => {
            status.setFileProgress(file, 0);
            status.addFileIssue(file, window.__('legacy.fileManager.networkError'));
            reject('Network error occurred during upload');
        };

        // Aborted
        xhr.onabort = () => {
            status.setFileProgress(file, 0);
            reject('Upload aborted by user');
        };

        xhr.send(formData);
    });

    // Return both promise and abort method
    return {
        promise,
        abort: () => {
            if (xhr) xhr.abort();
        }
    };
}

/** @param {OldUiFileData} fileData */
async function downloadFile(fileData) {
    try {
        // Fetch the file as blob
        const response = await fetch(fileData.url);

        if (!response.ok) {
            throw new Error(`Download failed: ${response.statusText}`);
        }

        const blob = await response.blob();

        // Create a temporary object URL for the blob
        const objectUrl = URL.createObjectURL(blob);

        // Create a hidden link
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = fileData.name || 'download';

        // Trigger the download
        document.body.appendChild(link);
        link.click();

        // Cleanup
        document.body.removeChild(link);
        URL.revokeObjectURL(objectUrl);
        return true;

    } catch (err) {
        console.error('Download error:', err, fileData);
        alert('Failed to download file.');
        return false;
    }
}

/** @param {OldUiFileData} fileData */
async function previewFile(fileData) {
    try {
        const response = await fetch(fileData.url);
        const blob = await response.blob();

        const type = checkFileFormat(fileData.mime);

        switch (type) {
            case 'image':
                await renderImage(blob);
                break;
            case 'pdf':
                await renderPdf(blob);
                break;
            case 'docx':
                await renderDocx(blob);
                break;
            default:
                console.warn('Unsupported file type');
        }

        const modal = document.querySelector('#file-viewer-modal');
        modal.style.display = 'flex';
        const scrollContainer = modal.querySelector('#file-scroll-container');
        scrollContainer.scrollTop = 0;

        // ✅ return something meaningful to the caller
        return {success: true, type, blob};

    } catch (err) {
        console.error('Error in previewFile:', err);
        return Promise.reject(err);
    }
}

async function renderPdf(blob) {

    const arrayBuffer = await blob.arrayBuffer();

    const pdf = await pdfjsLib.getDocument({data: arrayBuffer}).promise;

    const container = document.getElementById('file-preview-container');
    container.innerHTML = ''; // Clear previous pages

    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        const pdfPage = await pdf.getPage(pageNum);
        const viewport = pdfPage.getViewport({scale: 1});

        // ── Page wrapper ───────────────────────────────────────────────
        const pageDiv = document.createElement('div');
        pageDiv.className = 'pdf-page';
        pageDiv.style.position = 'relative';
        pageDiv.style.margin = '1rem auto';
        pageDiv.style.width = '100%';
        pageDiv.style.maxWidth = `${viewport.width}px`;

        // ── Responsive canvas ─────────────────────────────────────────
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        canvas.style.width = '100%';
        canvas.style.height = 'auto';
        canvas.style.display = 'block';
        pageDiv.appendChild(canvas);

        await pdfPage.render({canvasContext: context, viewport}).promise;

        // ── Text layer ────────────────────────────────────────────────
        // const textLayerBuilder = new TextLayerBuilder({
        //     pdfPage,
        //     textLayerMode: 2 // Use enhanced layout for better accuracy
        // });
        // console.log(textLayerBuilder)
        // await textLayerBuilder.render({ viewport });

        // const textLayerDiv = textLayerBuilder.div;
        // textLayerDiv.style.position = 'absolute';
        // textLayerDiv.style.top  = '0';
        // textLayerDiv.style.left = '0';
        // textLayerDiv.style.width  = '100%';
        // textLayerDiv.style.height = '100%';

        // pageDiv.appendChild(textLayerDiv);

        // ── Append to container ───────────────────────────────────────
        container.appendChild(pageDiv);
    }

}

async function renderDocx(blob) {
    const container = document.getElementById('file-preview-container');
    container.innerHTML = '';

    docxPreview.renderAsync(blob, container)
        .then(x => console.log('docx: finished'));
}

async function renderImage(blob) {
    const container = document.getElementById('file-preview-container');
    container.innerHTML = '';


    // Create a local URL for the blob
    const url = URL.createObjectURL(blob);

    // Create an <img> element
    const img = document.createElement('img');
    img.src = url;
    img.classList.add('image-preview');

    // Optionally: Clean up the object URL after image loads to avoid memory leaks
    img.onload = () => {
        URL.revokeObjectURL(url);
    };


    const wrapper = document.createElement('div');
    wrapper.classList.add('image-preview-wrapper');

    // Append the image to the DOM, e.g., to the body or a specific container
    wrapper.appendChild(img);
    container.appendChild(wrapper);

}


function checkFileFormat(mime) {
    if (mime.startsWith('image/')) {
        return 'image';
    } else if (mime.includes('pdf')) {
        return 'pdf';
    } else if (mime.includes('msword') ||
        mime.includes('wordprocessingml')) {
        return 'docx';
    } else {
        return 'document';
    }
}


// Generate a unique ID for the file
function generateUniqueId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
}
