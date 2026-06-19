// Ensure "waitUntilReady" is defined
window.__earlyWaitUntilReadyQueue = [];
if (typeof window.waitUntilReady !== 'function') {
    window.waitUntilReady = function (callback) {
        window.__earlyWaitUntilReadyQueue.push(callback);
    };
}

//#region Overlay
async function setOverlay(activation, smooth = true) {
    const overlay = document.getElementById('overlay');

    overlay.style.transition = `opacity ${smooth ? 500 : 0}ms`;

    if (activation) {
        overlay.style.visibility = 'visible'; // Make it visible first
        overlay.style.opacity = '1';          // Transition the opacity
        await new Promise(resolve => setTimeout(resolve, 1000));
    } else {
        overlay.style.opacity = '0';          // Fade out the opacity
        // Wait for the opacity transition to finish before hiding
        await new Promise(resolve => setTimeout(resolve, 1000));
        overlay.style.visibility = 'hidden';  // Now hide it after the fade-out
    }
}

//#endregion

async function logout() {
    await setOverlay(true, true);
    window.location.href = '/logout';
}


window.waitUntilReady(function () {
    // Function to initialize tooltip logic for a given tooltip-parent element
    function setupTooltip(ttp) {
        if (ttp.dataset.tooltipInit === 'true') return;
        ttp.dataset.tooltipInit = 'true';
        const tt = ttp.querySelector('.tooltip');
        if (!tt) {
            return;
        }
        tt.style.display = 'none';
        let hoverTimer;

        ttp.addEventListener('mouseenter', function (e) {
            hoverTimer = setTimeout(() => {
                tt.style.display = 'flex';
            }, 700);
        });

        ttp.addEventListener('mouseleave', function (e) {
            clearTimeout(hoverTimer);
            tt.style.display = 'none';
        });
    }

    // Initialize existing tooltip parents
    document.querySelectorAll('.tooltip-parent').forEach(setupTooltip);

    // Watch for dynamically added tooltip-parent elements
    const observer = new MutationObserver(mutations => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType !== Node.ELEMENT_NODE) continue;

                if (node.classList.contains('tooltip-parent')) {
                    setupTooltip(node);
                }
                node.querySelectorAll?.('.tooltip-parent').forEach(setupTooltip);
            }
        }
    });

    // Observe the entire body for added nodes
    observer.observe(document.body, {childList: true, subtree: true});
});


window.waitUntilReady(function () {
    document.querySelectorAll('.hint-btn').forEach(el => {
        el.addEventListener('click', function (e) {
            const hintBox = document.getElementById(el.dataset.hintId);
            hintBox.classList.toggle('active');
        });
    });
});

/**
 * Opens a modal dialog with the specified message and optional header.
 * @param {string} message The message to display in the modal.
 * @param {string} [header] An optional header to display above the message.
 * @param {(messageEl: HTMLDivElement, resolve: ((value: boolean)=> void)) => void} [onOpen] An optional callback function that will be called after the modal is created, allowing for custom initialization of the modal content.
 * @return Promise<boolean> Resolves to true if the user confirms, false if they cancel.
 */
function modalConfirm(message, header, onOpen) {
    return openModal(ModalType.CONFIRM, message, header, onOpen);
}

/**
 * Opens a modal dialog with the specified message and optional header.
 * @param {string} message The message to display in the modal.
 * @param {string} [header] An optional header to display above the message.
 * @param {(messageEl: HTMLDivElement, resolve: ((value: boolean)=> void)) => void} [onOpen] An optional callback function that will be called after the modal is created, allowing for custom initialization of the modal content.
 * @return Promise<boolean> Resolves to true if the user confirms, false if they cancel.
 */
function modalWarning(message, header, onOpen) {
    return openModal(ModalType.WARNING, message, header, onOpen);
}

/**
 * Opens a modal dialog with the specified message and optional header.
 * @param {string} message The message to display in the modal.
 * @param {string} [header] An optional header to display above the message.
 * @param {(messageEl: HTMLDivElement, resolve: (()=> void)) => void} [onOpen] An optional callback function that will be called after the modal is created, allowing for custom initialization of the modal content.
 * @return Promise<null> Resolves to null when the user closes the modal.
 */
function modalInfo(message, header, onOpen) {
    return openModal(ModalType.INFO, message, header, onOpen);
}

/**
 * Opens a modal dialog with the specified message and optional header.
 * @param {string} message The message to display in the modal.
 * @param {string} [header] An optional header to display above the message.
 * @return Promise<null> Resolves to null when the user closes the modal.
 * @param {(messageEl: HTMLDivElement, resolve: (()=> void)) => void} [onOpen] An optional callback function that will be called after the modal is created, allowing for custom initialization of the modal content.
 */
function modalError(message, header, onOpen) {
    console.error('ERROR MODAL', message);
    return openModal(ModalType.ERROR, message, header, onOpen);
}
