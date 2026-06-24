//#region Requests And Redirections

function initializeGUI() {

    //prepare text areas
    const textareas = document.querySelectorAll('.singleLineTextarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault(); // Prevent the default behavior, which is to insert a newline
            }
        });
    });
    const root = document.querySelector(':root');
    root.style.setProperty('--transition-medium', '0');
}


function onSidebarButtonDown(pageID) {
    if (pageID === activeModule) {
        if (document.getElementById(`${pageID}-sidebar`) != null) {
            togglePanelClass(`${pageID}-sidebar`, 'expanded');

            document.querySelector('.dy-main-content').classList.toggle('expanded');

            const sidebar = document.getElementById(`${pageID}-sidebar`);
            const manualExpanded = sidebar.classList.contains('expanded');
            sidebar.dataset.manualExpanded = manualExpanded;

            const selectId = pageID === 'groupchat' ? 'chat' : pageID;
            const content = document.getElementById(selectId);
            if (content) {
                if (manualExpanded) {
                    const windowWidth = window.innerWidth;
                    content.style.minWidth = `${windowWidth - 100}px`;
                } else {
                    content.style.minWidth = '';
                }
            }
        }
    } else {
        redirectToModule(pageID);
    }
}

function redirectToModule(pageID) {
    window.location.href = `/${pageID}`;
}

function setActiveSidebarButton(activeModule) {

    const sidebarButtons = document.querySelectorAll('.sidebar-btn');
    const targetId = `${activeModule}-sb-btn`;

    sidebarButtons.forEach(sbb => {
        if (sbb.classList.contains('active')) {
            sbb.classList.remove('active');
        }
    });

    document.getElementById(targetId).classList.add('active');
}


//#endregion


// //#region Modals
function modalClick(button) {
    const modal = button.closest('.modal');
    localStorage.setItem(modal.id, 'true');
    modal.remove();
}

function CheckModals() {
    const modals = document.querySelectorAll('.modal');
    for (let i = 0; i < modals.length; i++) {
        const modal = modals[i];
        if (localStorage.getItem(modal.id) === 'true') {
            modal.remove();
        } else if (typeof modal.init === 'function') {
            modal.init();
        }
    }
}

// //#endregion


//#region Panel Controls
function togglePanelClass(targetID, className) {
    const panel = document.getElementById(targetID);
    panel.classList.toggle(className);
}

function toggleRelativePanelClass(targetID, sender, className, activation = null) {
    let currentElement = sender;

    while (currentElement) {
        if (currentElement.id === targetID) {
            currentElement.classList.toggle(className);
            return;
        }

        let parentElement = currentElement.parentElement;
        if (parentElement) {
            let siblings = parentElement.children;
            for (let sibling of siblings) {
                if (sibling.id === targetID) {
                    switch (activation) {
                        case true:
                            sibling.classList.add(className);
                            break;
                        case false:
                            sibling.classList.remove(className);
                            break;
                        case null:
                            sibling.classList.toggle(className);
                            break;
                    }
                    return;
                }
            }
        }
        currentElement = parentElement;
    }
}

//#endregion


//#region Burgers & Dropdown Click Events


function closeModal(closeBtn) {
    const modal = closeBtn.closest('.modal');
    modal.style.display = 'none';

}

function playSound(type) {

    let audioFile;
    let vol = 1.0;
    switch (type) {
        case 'in':
            vol = 0.5;
            audioFile = '../audio/click.mp3';
            break;
        case 'out':
            audioFile = '../audio/notification1.mp3';
            break;
        case 'alert':
            audioFile = '../audio/notification1.mp3';
            break;
        default:
            console.error('Unknown notification type:', type);
            return;
    }

    const audio = new Audio(audioFile);
    audio.volume = vol;

    audio.play().catch((error) => {
        console.error('Error playing sound:', error);
    });


}


// #region reaction Buttons

// Function to handle button scaling and reaction display

function reactionMouseDown(button) {
    button.style.transform = 'scale(1.1)';
}

function reactionMouseUp(button) {
    // Reset scale on mouse up
    button.style.transform = 'scale(1.0)';

    // Handle reaction display
    const reaction = button.querySelector('.reaction');
    if (reaction) {
        reaction.style.display = 'block';
        setTimeout(() => {
            reaction.style.opacity = '1';
        }, 50);

        // Hide the reaction after 3 seconds
        setTimeout(() => {
            reaction.style.opacity = '0';

            // Set display to none after the fade-out transition
            setTimeout(() => {
                reaction.style.display = 'none';
            }, 500); // Match transition duration
        }, 3000); // Time before fading starts
    }
}

//#endregion


function checkWindowSize(thresholdWidth, thresholdHeight) {
    let debounceTimer;

    // Function to check if window size is smaller than the threshold
    function onResize() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const currentWidth = window.innerWidth;
            const currentHeight = window.innerHeight;
            const sidebar = document.getElementById(`${activeModule}-sidebar`) ? document.getElementById(`${activeModule}-sidebar`) : null;
            if (currentWidth < thresholdWidth || currentHeight < thresholdHeight) {
                if (sidebar) {
                    if (!sidebar.dataset.manualExpanded) {
                        document.getElementById(`${activeModule}-sidebar`).classList.remove('expanded');
                        document.querySelector('.dy-main-content').classList.remove('expanded');
                    }
                }
            } else {

                if (sidebar) {
                    if (!sidebar.dataset.manualExpanded) {
                        document.getElementById(`${activeModule}-sidebar`).classList.add('expanded');
                        document.querySelector('.dy-main-content').classList.add('expanded');

                    }
                }
            }
        }, 100);
    }

    // Add event listener for the 'resize' event
    window.addEventListener('resize', onResize);

    onResize();
    setTimeout(() => {
        const root = document.querySelector(':root');
        root.style.setProperty('--transition-medium', '500ms');
    }, 100);

}

function setSessionCheckerTimer(time) {
    setTimeout(() => {
        fetch('/check-session')
            .then(response => response.json())
            .then(data => {

                if (data.expired || data.remaining === 0) {
                    const expModal = document.getElementById('session-expiry-modal');
                    expModal.style.display = 'flex';
                } else {
                    setSessionCheckerTimer(data.remaining);
                }
            });
    }, time * 1000);
}
