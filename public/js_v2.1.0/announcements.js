
let announcement_queue = [];
let currentAnnouncementIndex = 0;
let anchored_queue;
let currentAnchoredAnnouncementIndex=0;

function initAnnouncements(announcementList){
    announcement_queue = (announcementList || []).filter(a => a.anchor === null);
    if (announcement_queue.length > 0) {
        currentAnnouncementIndex = 0;
        renderNextAnnouncement();
    }
}

function queueAnchoredAnnouncements(targetAnchor){
    anchored_queue = (announcementList || []).filter(a => a.anchor === targetAnchor);
    if (anchored_queue.length > 0) {
        currentAnnouncementIndex = 0;
        renderNextAnnouncementInQueue(anchored_queue);
    }
}


function renderNextAnnouncement(){
    if (currentAnnouncementIndex < announcement_queue.length) {
        renderAnnouncement(announcement_queue[currentAnnouncementIndex]);
    }
}

function renderNextAnnouncementInQueue(queue){
    if (currentAnchoredAnnouncementIndex < queue.length) {
        renderAnnouncement(queue[currentAnchoredAnnouncementIndex]);
    }
}


async function renderAnnouncement(announcement, show = true){
    try{
        // Request announcement render from server
        const response = await fetch(`/req/announcement/render/${announcement.id}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
        })
        const data = await response.json();
        if (data.success) {
            if (show) {
                // Create and show announcement modal
                showAnnouncementModal(announcement, data.view);
                // Mark as seen immediately when displayed
                markAnnouncementAsSeen(announcement.id);
            } else {
                // If show is false, return the server-rendered view
                return data.view;
            }
        }
        else{
            console.error('Error loading announcement:', error);
            // Skip to next announcement on error
            moveToNextAnnouncement();
        }
    }
    catch (error) {
        console.error('Error loading announcement:', error);
        // Skip to next announcement on error
        moveToNextAnnouncement();
    };
}

function showAnnouncementModal(announcement, view) {
    // Prepare regex to find [CONFIRM](...) and [DECLINE](...) tags
    const confirmTagRegex = /\[CONFIRM]\(([^)]+)\)/i;
    const declineTagRegex = /\[DECLINE]\(([^)]+)\)/i;

    // Extract button texts if present
    const confirmTag = confirmTagRegex.exec(view);
    const declineTag = declineTagRegex.exec(view);

    // Remove button tags from markdown before rendering
    let processedView = view.replace(confirmTagRegex, '').replace(declineTagRegex, '');
    // Render Markdown content
    const modal = document.querySelector('#announcements-modal');
    const contentWrapper = modal.querySelector('.content-box');
    const html = md.render(processedView);
    contentWrapper.innerHTML = html;

    // Set link targets and security attributes
    contentWrapper.querySelectorAll('a').forEach(a => {
        a.setAttribute('target', '_blank');
        a.setAttribute("rel", "noopener noreferrer");
    });

    // Create the button bar
    const btnBar = document.createElement('div');
    btnBar.classList.add('modal-buttons-bar');

    if (confirmTag || declineTag) {
        // Add buttons as defined in markdown tags
        if (declineTag) {
            const declineBtn = document.createElement('button');
            declineBtn.className = "btn-lg-stroke align-end";
            declineBtn.textContent = declineTag[1];
            declineBtn.addEventListener('click', () => {
                // Action for decline, customize as needed:
                if (announcement.isForced) {
                    forceLogoutUser();
                } else {
                    closeAnnouncementModal(modal);
                }
            });
            btnBar.appendChild(declineBtn);
        }
        if (confirmTag) {
            const confirmBtn = document.createElement('button');
            confirmBtn.className = "btn-lg-fill align-end";
            confirmBtn.textContent = confirmTag[1];
            confirmBtn.addEventListener('click', function() {
                reportAnnouncementFeedback(announcement.id);
                closeAnnouncementModal(modal);
            });
            btnBar.appendChild(confirmBtn);
        }
    } else {
        console.log(announcement);
        // Fallback: the default logic (Confirm, and Cancel if forced)
        const confirmBtn = document.createElement('button');
        confirmBtn.className = "btn-lg-fill align-end";
        confirmBtn.textContent = translation.Confirm;
        confirmBtn.addEventListener('click', function() {
            reportAnnouncementFeedback(announcement.id);
            closeAnnouncementModal(modal);
        });
        if (announcement.isForced == true) {
            const cancelBtn = document.createElement('button');
            cancelBtn.className = "btn-lg-stroke align-end";
            cancelBtn.textContent = translation.Cancel;
            cancelBtn.addEventListener('click', () => {
                forceLogoutUser();
            });
            btnBar.appendChild(cancelBtn);
        }
        btnBar.appendChild(confirmBtn);
    }
    if (btnBar.childElementCount == 1){
        btnBar.style.justifyContent = 'center';
    }

    contentWrapper.appendChild(btnBar);
    modal.style.display = 'flex';
}

function closeAnnouncementModal(annModal) {
    annModal.style.display = 'none';
    annModal.querySelector('.content-box').innerHTML = "";
    moveToNextAnnouncement();
}


async function forceLogoutUser(){
    const confirmed = await openModal(ModalType.WARNING , translation.Logout_Warning);
    if (!confirmed) {
        return;
    }
    logout();
}


function moveToNextAnnouncement() {
    currentAnnouncementIndex++;
    // Small delay before showing next announcement
    setTimeout(() => {
        renderNextAnnouncement();
    }, 300);
}

function markAnnouncementAsSeen(announcementId) {
    fetch(`/req/announcement/seen/${announcementId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        }
    })
    .catch(error => console.error('Error marking announcement as seen:', error));
}

function reportAnnouncementFeedback(announcementId){

    fetch(`/req/announcement/report/${announcementId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(`Announcement Confirmed`);
        }
    })
    .catch(error => console.error('Error reporting announcement feedback:', error));
}


async function fetchLatestPolicy() {
    try {
        const response = await fetch(`/req/announcement/fetchLatestPolicy`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) {
            return null;
        }
        const data = await response.json();
        if (data.success) {
            return {
                'announcement': data.announcement,
                'view': data.view
            }
        }
    } catch (error) {
        console.error('Error reporting announcement feedback:', error);
    }
}
