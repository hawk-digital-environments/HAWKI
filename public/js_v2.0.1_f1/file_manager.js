
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


async function requestFileUrl(uuid, category, filename){
    try {
        const response = await fetch(`/req/${category}/attachment/getLink/${uuid}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
        });

        const data = await response.json();

        if (data.success && data.url) {
        // Automatically start download

        return data.url;

        } else {
        alert('Failed to get download link');
        }
    } catch (err) {
        console.error('Download error:', err);
        alert('An error occurred while requesting the file.');
    }
}

// async function downloadAttachment(uuid, category){

//     const requestObj = json.stringify({
//         'category': category,
//         'uuid': uuid
//     });

//     try {
//         // Send request to server
//         const response = await fetch('/req/download-file', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json',
//                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
//             },
//             body: requestObj
//         });

//         if (!response.ok) {
//             throw new Error('Network response was not ok');
//         }
//         const data = await response.json();
//         console.log(data)
//         updateFileStatus(data.requestId, 'complete', data.fileUrl);
//         return data;
//     } catch (error) {
//         console.error('Upload error:', error);
//         updateFileStatus(fileData.tempId, 'error');
//         showFeedbackMsg(`Failed to upload file ${fileData.name}. Please try again.`);
//         throw error; // Re-throw to allow Promise.allSettled to catch it
//     }



// }

//#endregion


//#region PREVIEW

async function previewFile(provider, fileData, category) {

    const indicator = provider.querySelector('.status-indicator');




    const url = await requestFileUrl(fileData.uuid, category, fileData.filename)
    const response = await fetch(url);
    const blob = await response.blob();

    const type = checkFileFormat(fileData.mime);

    switch(type){
        case('img'):
            await renderImage(blob)
        break;
        case('pdf'):
            await renderPdf(blob);
        break;
        case('docx'):
            await renderDocx(blob);

        break;
    }
    document.querySelector('#file-viewer-modal').style.display = "flex";
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

function checkFileFormat(type){
    if (type.startsWith('image/')) {
        return 'img';
    } else if (type.includes('pdf')) {
        return 'pdf';
    } else if (type.includes('msword') ||
               type.includes('wordprocessingml')) {
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
