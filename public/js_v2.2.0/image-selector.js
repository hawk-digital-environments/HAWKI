
let croppedImageData;
let currentImageCallback = null;
let imageField;
let placeholder
let cropper;


// Open the modal and set the current element and title dynamically
// callback function parameter to handle different actions after the image is selected
function openImageSelection(currentImageUrl, callback) {
    // Existing code to set up the modal
    const imageModal = document.getElementById('image-selection-modal');

    currentImageCallback = callback;     // Store the callback function
    imageModal.style.display = 'flex';

    imageField = imageModal.querySelector('#image-field');
    placeholder = imageModal.querySelector('#image-field-placeholder');
    if(currentImageUrl){
        imageField.style.display = 'block';
        placeholder.style.display = 'none';

        imageField.setAttribute('src', currentImageUrl);
        setupCropper();
    }
    else{
        imageField.style.display = 'none';
        placeholder.style.display = 'flex';
    }

    // Initialize modal functionality
    initImageModal();
}


// Initialization function for setting up event listeners
function initImageModal() {
    const imageContainer = document.getElementById('image-container');

    imageContainer.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    imageContainer.addEventListener('dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    imageContainer.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            imageField.style.display = 'block';
            placeholder.style.display = 'none';

            handleFile(file);
        }
        // else {
        //     alert('Please upload a valid image.');
        // }
    });

    const imageFileInput = document.getElementById('image-file-input');
    if (imageFileInput) {
        imageFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {

                imageField.style.display = 'block';
                placeholder.style.display = 'none';

                handleFile(file);
            } else {
                alert('Please upload a valid image.');
            }
        });
    }
}

// Handle file upload (either drag & drop or file input)
function handleFile(file) {
    const reader = new FileReader();
    reader.onload = function(event) {
        const img = new Image();
        img.onload = function() {
            imgWidth = img.width;
            imgHeight = img.height;

            const $image = $('#image-field');
            $image.attr('src', event.target.result);

            // Ensure the image dimensions are calculated correctly
            setTimeout(() => {
                setupCropper();
            }, 100); // Use a timeout to ensure rendering
        };
        img.src = event.target.result;
    };
    reader.readAsDataURL(file);
}


function setupCropper() {
    const imageElement = document.getElementById('image-field');

    // Destroy the previous cropper instance, if any
    if (cropper) {
        cropper.destroy();
    }

    // Initialize the CropperJS v2 with custom template
    // The template uses web components and sets aspectRatio on cropper-selection
    const template = (
        '<cropper-canvas background>'
            + '<cropper-image></cropper-image>'
            + '<cropper-shade hidden></cropper-shade>'
            + '<cropper-handle action="select" plain></cropper-handle>'
            + '<cropper-selection initial-coverage="1" aspect-ratio="1" movable resizable>'
                + '<cropper-grid role="grid" bordered covered></cropper-grid>'
                + '<cropper-crosshair centered></cropper-crosshair>'
                + '<cropper-handle action="move" theme-color="rgba(255, 255, 255, 0.35)"></cropper-handle>'
                + '<cropper-handle action="n-resize"></cropper-handle>'
                + '<cropper-handle action="e-resize"></cropper-handle>'
                + '<cropper-handle action="s-resize"></cropper-handle>'
                + '<cropper-handle action="w-resize"></cropper-handle>'
                + '<cropper-handle action="ne-resize"></cropper-handle>'
                + '<cropper-handle action="nw-resize"></cropper-handle>'
                + '<cropper-handle action="se-resize"></cropper-handle>'
                + '<cropper-handle action="sw-resize"></cropper-handle>'
            + '</cropper-selection>'
        + '</cropper-canvas>'
    );

    cropper = new Cropper(imageElement, {
        template: template
    });
}




// Save the cropped image and update the original element
// When the image is saved, execute the callback function
function saveCroppedImage() {
    if (!cropper) {
        console.error('Cropper instance not found');
        return;
    }

    // Get the cropper selection element (v2 API)
    const selection = cropper.getCropperSelection();
    
    if (!selection) {
        console.error('Cropper selection not found');
        return;
    }

    // Using CropperJS v2 to get the resulting cropped canvas (returns a Promise)
    selection.$toCanvas()
        .then((croppedCanvas) => {
            if (!croppedCanvas) {
                console.error('Failed to get cropped canvas');
                return;
            }

            return resizeImage(croppedCanvas, 1024);
        })
        .then((resizedCanvas) => {
            // Convert canvas to Blob
            resizedCanvas.toBlob(function(blob) {
                if (!blob) {
                    console.error('Failed to get cropped blob');
                    return;
                }
                
                // Call the callback with the Blob
                if (typeof currentImageCallback === 'function') {
                    currentImageCallback(blob);
                }
                closeImageSelector();
            }, 'image/jpeg');
        })
        .catch((err) => {
            console.error('Error processing image:', err);
        });
}


// Close the modal
function closeImageSelector() {
    const imageModal = document.getElementById('image-selection-modal');
    imageModal.style.display = 'none';

    const modalImageField = imageModal.querySelector('#image-field');

    // Clear image styles and source
    modalImageField.style.width = '';
    modalImageField.style.height = '';
    modalImageField.removeAttribute('src');
    // Destroy the Cropper.js instance if it exists
    if (cropper) {
        cropper.destroy();
        cropper = null; // Clear the reference to ensure the next image reload creates a new instance
    }
}


function resizeImage(canvas, maxSize) {
    return new Promise((resolve, reject) => {
        const originalWidth = canvas.width;
        const originalHeight = canvas.height;

        // Check if resizing is needed
        if (originalWidth <= maxSize && originalHeight <= maxSize) {
            resolve(canvas); // No need to resize, return the original canvas
            return;
        }

        let newWidth, newHeight;

        // Calculate new dimensions while maintaining aspect ratio
        if (originalWidth > originalHeight) {
            newWidth = maxSize;
            newHeight = (originalHeight * maxSize) / originalWidth;
        } else {
            newHeight = maxSize;
            newWidth = (originalWidth * maxSize) / originalHeight;
        }

        // Create a new canvas for the resized image
        const resizeCanvas = document.createElement('canvas');
        const resizeContext = resizeCanvas.getContext('2d');

        resizeCanvas.width = newWidth;
        resizeCanvas.height = newHeight;

        // Draw the resized image on the new canvas
        resizeContext.drawImage(canvas, 0, 0, originalWidth, originalHeight, 0, 0, newWidth, newHeight);

        // Return the resized canvas
        resolve(resizeCanvas);
    });
}
