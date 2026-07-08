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

    const sidebarEls = findSidebarAndContent(findActiveSidebarType());
    if (sidebarEls) {
        let debounceTimer;

        function debouncedOnResize() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                if (!window.oldUiMessageHistory.isInConversation) {
                    openSidebar(sidebarEls.type);
                    return;
                }
                if (sidebarIsMobileStyle(sidebarEls.sidebarEl)) {
                    closeSidebar(sidebarEls.type);
                }
            }, 100);
        }

        window.addEventListener('resize', debouncedOnResize);
        debouncedOnResize();

        function handleCloseOnClick() {
            if (sidebarIsMobileStyle(sidebarEls.sidebarEl)) {
                closeSidebar(sidebarEls.type);
            }
        }

        sidebarEls.content.addEventListener('click', handleCloseOnClick);
        if (sidebarEls.welcomeContent) {
            sidebarEls.welcomeContent.addEventListener('click', handleCloseOnClick);
        }
    }

}

/**
 * @param {'groupchat'|'chat'}sidebarType
 * @return {{sidebarEl: HTMLElement, content: HTMLElement, welcomeContent: HTMLElement|undefined, type: string}|null}
 */
function findSidebarAndContent(sidebarType) {
    if (sidebarType !== 'groupchat' && sidebarType !== 'chat') {
        return null;
    }
    const sidebarEl = document.getElementById(`${sidebarType}-sidebar`);
    const selectId = sidebarType === 'groupchat' ? 'chat' : sidebarType;
    const content = document.getElementById(selectId);
    const welcomeContent = document.getElementById('group-welcome-panel');
    return {sidebarEl, content, welcomeContent, type: sidebarType};
}

function findActiveSidebarType() {
    const sidebarEl = document.getElementById('groupchat-sidebar') || document.getElementById('chat-sidebar');
    if (!sidebarEl) {
        return null;
    }
    return sidebarEl.id === 'groupchat-sidebar' ? 'groupchat' : 'chat';
}

function sidebarIsMobileStyle(sidebar = null) {
    if (!sidebar) {
        const els = findSidebarAndContent(findActiveSidebarType());
        if (els) {
            sidebar = els.sidebarEl;
        } else {
            return null;
        }
    }

    return getComputedStyle(sidebar).getPropertyValue('--sidebar-mobile-style').trim() === '1';
}

function openSidebar(sidebarType = null) {
    const els = findSidebarAndContent(sidebarType ?? findActiveSidebarType());
    if (els) {
        const {sidebarEl, content, welcomeContent} = els;
        sidebarEl.classList.add('expanded');
        content.classList.add('expanded');
        if (sidebarIsMobileStyle(sidebarEl)) {
            const windowWidth = window.innerWidth;
            content.style.minWidth = `${windowWidth}px`;
            if (welcomeContent) {
                welcomeContent.style.minWidth = `${windowWidth}px`;
            }
        } else {
            content.style.minWidth = '';
            if (welcomeContent) {
                welcomeContent.style.minWidth = '';
            }
        }
    }
}

function closeSidebar(sidebarType = null) {
    const els = findSidebarAndContent(sidebarType ?? findActiveSidebarType());
    if (els) {
        const {sidebarEl, content} = els;
        sidebarEl.classList.remove('expanded');
        content.classList.remove('expanded');
        content.style.minWidth = '';
        if (els.welcomeContent) {
            els.welcomeContent.style.minWidth = '';
        }
    }
}

function onSidebarButtonDown(pageID, switchConversations = false) {
    if (pageID === activeModule) {
        const els = findSidebarAndContent(pageID);
        if (els != null) {
            let isExpanded = els.sidebarEl.classList.contains('expanded');
            let shouldBeExpand = !isExpanded;

            if (switchConversations) {
                shouldBeExpand = !sidebarIsMobileStyle(els.sidebarEl);
            }

            if (shouldBeExpand) {
                openSidebar(pageID);
            } else {
                closeSidebar(pageID);
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
