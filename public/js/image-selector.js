
let currentImageCallback = null;
let placeholder
let cropper;


// Open the modal and set the current element and title dynamically
// callback function parameter to handle different actions after the image is selected
function openImageSelection(currentImageUrl, callback) {
    // Existing code to set up the modal
    const imageModal = document.getElementById('image-selection-modal');

    currentImageCallback = callback;     // Store the callback function
    imageModal.style.display = 'flex';

    placeholder = imageModal.querySelector('#image-field-placeholder');
    if (currentImageUrl) {
        if (placeholder) {
            placeholder.style.display = 'none';
        }
        setupCropper(currentImageUrl);
    } else if (placeholder) {
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
            if (placeholder) {
                placeholder.style.display = 'none';
            }

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
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
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
            setupCropper(event.target.result);
        };
        img.src = event.target.result;
    };
    reader.readAsDataURL(file);
}


function setupCropper(currentImageUrl) {
    // Destroy the previous cropper instance, if any
    if (cropper) {
        cropper.destroy();
    }

    // Initialize the CropperJS v2 with custom template
    // The template uses web components and sets aspectRatio on cropper-selection
    const template = (
        '<cropper-canvas background style="width: 100%;">'
        + '<cropper-image src="' + currentImageUrl + '" id="cropper-selector-image"></cropper-image>'
        + '<cropper-shade hidden id="cropper-shade"></cropper-shade>'
            + '<cropper-handle action="select" plain></cropper-handle>'
        + '<cropper-selection initial-coverage="0.95" aspect-ratio="1" movable resizable zoomable outlined keyboard id="cropper-selection">'
        + '<cropper-handle action="move" theme-color="transparent"></cropper-handle>'
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

    document.getElementById('image-container').innerHTML = template;
    const image = document.getElementById('cropper-selector-image');
    const shade = document.getElementById('cropper-shade');
    const selection = document.getElementById('cropper-selection');
    image.$ready(() => {
        if (shade.shadowRoot) {
            shade.style.borderRadius = '50%';
        }
        if (selection.shadowRoot) {
            selection.style.borderRadius = '50%';
        }
        image.scalable = true;
        image.translatable = true;
        image.$center('contain');
        setTimeout(() => {
            image.scalable = false;
            image.translatable = false;
        });
    });
}




// Save the cropped image and update the original element
// When the image is saved, execute the callback function
function saveCroppedImage() {
    const selection = document.getElementById('cropper-selection');

    if (!selection) {
        console.error('Cropper selection not found');
        return;
    }

    // Using CropperJS v2 to get the resulting cropped canvas (returns a Promise)
    selection.$toCanvas({width: 512, height: 512})
        .then((croppedCanvas) => {
            if (!croppedCanvas) {
                throw new Error('Failed to get cropped canvas');
            }

            // Convert canvas to Blob
            croppedCanvas.toBlob(function (blob) {
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

    const imageContainer = document.getElementById('image-container');
    imageContainer.innerHTML = ''; // Clear the container
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
