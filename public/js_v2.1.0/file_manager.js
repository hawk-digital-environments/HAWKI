
//#region UPLOAD DOWNLOAD

/**
 * Upload a file with progress tracking and cancel support.
 *
 * @param {object} fileData - The file metadata and File/Blob object.
 * @param {string} url - The server upload URL.
 * @param {function} progressCallback - Called with (tempId, status, percent, fileUrl).
 * @returns {{ promise: Promise<object>, abort: () => void }}
 */
function uploadFileToServer(fileData, url, progressCallback) {
    let xhr = new XMLHttpRequest();

    const promise = new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('file', fileData.file);
        const tempId = fileData.tempId;

        // Initial progress state
        if (progressCallback) {
            progressCallback(tempId, 'uploading', 0);
        }

        xhr.open('POST', url, true);

        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken.getAttribute('content'));
        }

        // Upload progress tracking
        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable && progressCallback) {
                const percent = Math.round((event.loaded / event.total) * 100);
                progressCallback(tempId, 'uploading', percent);
            }
        };

        // Upload success
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const responseData = JSON.parse(xhr.responseText);
                    if (progressCallback) {
                        progressCallback(responseData.requestId || tempId, 'complete', 100, responseData.fileUrl);
                    }
                    resolve(responseData);
                } catch (e) {
                    progressCallback?.(tempId, 'error', 100);
                    reject('Invalid server response');
                }
            } else {
                progressCallback?.(tempId, 'error', 100);
                reject(`Upload failed: ${xhr.statusText}`);
            }
        };

        // Network error
        xhr.onerror = () => {
            progressCallback?.(tempId, 'error', 100);
            reject('Network error occurred during upload');
        };

        // Aborted
        xhr.onabort = () => {
            progressCallback?.(tempId, 'aborted', 0);
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


async function requestFileUrl(uuid, category){
    try {
        const response = await fetch(`/req/${category}/attachment/getLink/${uuid}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });

        const data = await response.json();

        if (data.success && data.url) {
        // Automatically start download

        return data.url;

        } else {
        console.error('Failed to get download link');
        }
    } catch (err) {
        console.error('Download error:', err);
        console.error('An error occurred while requesting the file.');
    }
}

async function downloadFile(uuid, category, filename) {
    try {
        // Get signed file URL from your backend
        const url = await requestFileUrl(uuid, category);
        console.log(url);
        // Fetch the file as blob
        const response = await fetch(url);
        console.log(response);
        if (!response.ok) {
            throw new Error(`Download failed: ${response.statusText}`);
        }

        const blob = await response.blob();

        // Create a temporary object URL for the blob
        const objectUrl = URL.createObjectURL(blob);

        // Create a hidden link
        const link = document.createElement("a");
        link.href = objectUrl;
        link.download = filename || "download";

        // Trigger the download
        document.body.appendChild(link);
        link.click();

        // Cleanup
        document.body.removeChild(link);
        URL.revokeObjectURL(objectUrl);
        return true;

    } catch (err) {
        console.error("Download error:", err);
        alert("Failed to download file.");
        return false;
    }
}


//#endregion


//#region PREVIEW

async function previewFile(provider, fileData, category) {
    const indicator = provider.querySelector('.status-indicator');

    try {
        const url = await requestFileUrl(fileData.uuid, category);
        if (!url) {
            console.log('No download link');
            return Promise.reject(new Error('No download link'));
        }

        const response = await fetch(url);
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

        modal.style.display = "flex";
        const scrollContainer = modal.querySelector('#file-scroll-container');
        scrollContainer.scrollTop = 0;

        // ✅ return something meaningful to the caller
        return { success: true, type, blob };

    } catch (err) {
        console.error('Error in previewFile:', err);
        return Promise.reject(err);
    }
}

function scrollToTop(){
    const modal = document.querySelector('#file-viewer-modal');
    const scrollContainer = modal.querySelector('#file-scroll-container');
    scrollContainer.scrollTop = 0;
}

async function renderPdf(blob) {

    const arrayBuffer = await blob.arrayBuffer();

    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

    const container = document.getElementById('file-preview-container');
    container.innerHTML = ''; // Clear previous pages

    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
        const pdfPage  = await pdf.getPage(pageNum);
        const viewport = pdfPage.getViewport({ scale: 1 });

        // ── Page wrapper ───────────────────────────────────────────────
        const pageDiv = document.createElement('div');
        pageDiv.className = 'pdf-page';
        pageDiv.style.position = 'relative';
        pageDiv.style.margin = '1rem auto';
        pageDiv.style.width = '100%';
        pageDiv.style.maxWidth = `${viewport.width}px`;

        // ── Responsive canvas ─────────────────────────────────────────
        const canvas   = document.createElement('canvas');
        const context  = canvas.getContext('2d');
        canvas.width   = viewport.width;
        canvas.height  = viewport.height;
        canvas.style.width  = '100%';
        canvas.style.height = 'auto';
        canvas.style.display = 'block';
        pageDiv.appendChild(canvas);

        await pdfPage.render({ canvasContext: context, viewport }).promise;

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

async function renderDocx(blob){
    const container = document.getElementById('file-preview-container');
    container.innerHTML = '';

    docxPreview.renderAsync(blob, container)
        .then(x => console.log("docx: finished"));
}

async function renderImage(blob){
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



//#endregion



//#region Utils

function checkFileFormat(mime){
    if (mime.startsWith('image/')) {
        return 'image';
    } else if (mime.includes('pdf')) {
        return 'pdf';
    } else if (mime.includes('msword') ||
               mime.includes('wordprocessingml')) {
        return 'docx';
    } else {
        return null;
    }
}


// Generate a unique ID for the file
function generateUniqueId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
}

//#endregion
