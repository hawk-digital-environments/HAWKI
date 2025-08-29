
let announcement_queue = [];
let currentAnnouncementIndex = 0;

function initAnnouncements(announcementList){
    // create a queue
    announcement_queue = announcementList || [];
    console.log(announcement_queue);
    if (announcement_queue.length > 0) {
        currentAnnouncementIndex = 0;
        renderNextAnnouncement();
    }
}

function renderNextAnnouncement(){
    if (currentAnnouncementIndex < announcement_queue.length) {
        renderAnnouncement(announcement_queue[currentAnnouncementIndex]);
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
                'Content-Type': 'application/json'
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
    // Insert the rendered content
    const modal = document.querySelector('#announcements-modal');
    const contentWrapper = modal.querySelector('.content-box');
    const html = md.render(view);
    contentWrapper.innerHTML = html;

    contentWrapper.querySelectorAll('a').forEach(a => {
        a.setAttribute('target', '_blank');
        a.setAttribute("rel", "noopener noreferrer");
    });


    const btnBar = document.createElement('div');
    btnBar.classList.add('modal-buttons-bar');


    const confirmBtn = document.createElement('button');
    confirmBtn.className = "btn-lg-fill align-end";
    confirmBtn.textContent = translation.Confirm;
    confirmBtn.addEventListener('click', function() {
        reportAnnouncementFeedback(announcement.id); // or whatever your parameters are
        closeAnnouncementModal(modal);
    });
    console.log(announcement);
    if(announcement.isForced == true){
        const cancelBtn = document.createElement('button');
        cancelBtn.className = "btn-lg-stroke align-end";
        cancelBtn.textContent = translation.Cancel;
        cancelBtn.addEventListener('click', () => {
            forceLogoutUser();
        });
        btnBar.appendChild(cancelBtn);
    }
    btnBar.appendChild(confirmBtn);

    contentWrapper.appendChild(btnBar);

    modal.style.display = 'flex'

}

function closeAnnouncementModal(annModal) {
    annModal.style.display = 'none';
    annModal.querySelector('.content-box').innerHTML = "";
    moveToNextAnnouncement();
}


async function forceLogoutUser(){
    const confirmed = await openModal(ModalType.WARNING , "To contninue using HAWKI you need to accept this. click on confirm to logout from HAWKI.");
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
            'Content-Type': 'application/json'
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
            'Content-Type': 'application/json'
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
                'Content-Type': 'application/json'
            },
        });
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
