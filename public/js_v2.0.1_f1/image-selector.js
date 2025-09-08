
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

    // Initialize the CropperJS
    cropper = new Cropper(imageElement, {
        viewMode: 1,                // Restrict the crop box to not exceed the size of the canvas
        dragMode: 'crop',           // Enable the drag mode when the image is ready
        autoCropArea: 1,            // Define the initial aspect ratio of the crop box
        responsive: true,           // Make the crop box responsive
        restore: false,
        modal: true,
        guides: true,
        highlight: true,
        background: false,          // Hide the viewport background
        cropBoxResizable: true,     // Allow resizing the crop box
        cropBoxMovable: true,       // Allow moving the crop box
        toggleDragModeOnDblclick: false, // Disable double-click switching
        aspectRatio: 1,             // Enforce 1:1 aspect ratio for the crop box
        zoomable: false,            // Disable zooming (both mouse wheel and pinch)
        wheelZoomRatio: 0           // Specifically disable zoom on mouse scroll
    });
}




// Save the cropped image and update the original element
// When the image is saved, execute the callback function
function saveCroppedImage() {
    if (!cropper) {
        console.error('Cropper instance not found');
        return;
    }

    // Get the cropping data using CropperJS
    const cropData = cropper.getData(true); // Use `true` to get rounded values

    // Using CropperJS to get the resulting cropped canvas
    const croppedCanvas = cropper.getCroppedCanvas();

    if (!croppedCanvas) {
        console.error('Failed to get cropped canvas');
        return;
    }

    resizeImage(croppedCanvas, 1024)
        .then((resizedCanvas) => {
            // NEW: convert canvas to Blob instead of base64
            resizedCanvas.toBlob(function(blob) {
                if (!blob) {
                    console.error('Failed to get cropped blob');
                    return;
                }
                console.log(blob);
                // If you want a File (with a name):
                // let file = new File([blob], "avatar.jpg", { type: "image/jpeg" });

                // Call the callback with the Blob (or File)
                if (typeof currentImageCallback === 'function') {
                    currentImageCallback(blob); // or file
                }
                closeImageSelector();
            }, 'image/jpeg');

        closeImageSelector();
    }).catch((err) => {
        console.error('Error resizing image:', err);
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
