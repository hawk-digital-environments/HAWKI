function initializeUserProfile(){
    const profile = document.getElementById('profile');


    const avatarDiv = profile.querySelector('.avatar-editable');
    if(userAvatarUrl){
        avatarDiv.querySelector('.user-inits').style.display = 'none';
        avatarDiv.querySelector('.icon-img').style.display = 'flex';
        avatarDiv.querySelector('.icon-img').setAttribute('src', userAvatarUrl);
    }
    else{
        avatarDiv.querySelector('.icon-img').style.display = 'none';
        const userInitials =  userInfo.name.slice(0, 1).toUpperCase();
        avatarDiv.querySelector('.user-inits').style.display = "flex";
        avatarDiv.querySelector('.user-inits').innerText = userInitials
    }


    profile.querySelector('#profile-name').innerText = userInfo.name;
    profile.querySelector('#profile-username').innerText = `@${userInfo.username}`;
    const bio = profile.querySelector('#bio-input').value = userInfo.bio;

}


async function selectProfileAvatar(btn){

    const imageElement = btn.querySelector('.selectable-image');
    const initials = btn.querySelector('.user-inits');

    // Define save callback
    const saveCallback = async function(croppedImage) {
        const imageUrl = await uploadProfileAvatar(croppedImage);

        imageElement.style.display = 'block';
        if(initials){
            initials.style.display = 'none';
        }

        imageElement.setAttribute('src', imageUrl);
        const sidebarBtn = document.getElementById('profile-sb-btn');
        sidebarBtn.querySelector('.profile-icon')
                  .querySelector('img')
                  .setAttribute('src', imageUrl);
        sidebarBtn.querySelector('.user-inits').style.display = 'none';
        sidebarBtn.querySelector('.icon-img').style.display = 'flex';
    };

    // Define delete callback (only if user has an avatar)
    const currentSrc = imageElement.getAttribute('src');
    const deleteCallback = currentSrc ? async function() {
        await removeProfileAvatar(imageElement, initials);
    } : null;

    openImageSelection(currentSrc, saveCallback, deleteCallback);
}

async function removeProfileAvatar(imageElement, initials) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const url = `/req/profile/removeAvatar`;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            }
        });
        const data = await response.json();

        if (data.success) {
            // Update profile avatar
            if (imageElement && initials) {
                imageElement.style.display = 'none';
                imageElement.setAttribute('src', '');
                initials.style.display = 'flex';
                
                // Get user initials from profile name
                const profileName = document.getElementById('profile-name');
                if (profileName && profileName.textContent) {
                    initials.textContent = profileName.textContent.slice(0, 2).toUpperCase();
                }
            }

            // Update sidebar avatar
            const sidebarBtn = document.getElementById('profile-sb-btn');
            if (sidebarBtn) {
                const sidebarIcon = sidebarBtn.querySelector('.icon-img');
                const sidebarInitials = sidebarBtn.querySelector('.user-inits');
                
                if (sidebarIcon && sidebarInitials) {
                    sidebarIcon.style.display = 'none';
                    sidebarIcon.setAttribute('src', '');
                    sidebarInitials.style.display = 'flex';
                    
                    const profileName = document.getElementById('profile-name');
                    if (profileName && profileName.textContent) {
                        sidebarInitials.textContent = profileName.textContent.slice(0, 2).toUpperCase();
                    }
                }
            }
            
            console.log('Profile avatar removed successfully');
        } else {
            console.error('Failed to remove avatar');
        }
    } catch (error) {
        console.error('Error removing profile avatar:', error);
    }
}

async function uploadProfileAvatar(image){
    const url = `/req/profile/uploadAvatar`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const formData = new FormData();
    formData.append('image', image);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            console.log('Image Uploaded Successfully');
            return data.url;

        } else {
            console.error('Upload not successful');
        }
    } catch (error) {
        console.error('Failed to upload image to server!');
    }

}

function checkBioUpdate(){
    const profile = document.getElementById('profile');
    const bio = profile.querySelector('#bio-input').innerText.trim();

    if(userInfo.bio != bio.trim()){
        profile.querySelector('.save-btn').style.display = 'block';
    }
    else{
        profile.querySelector('.save-btn').style.display = 'none';
    }
}

async function updateUserInformation(){

    const profile = document.getElementById('profile');

    // Trim and retrieve the current values of bio and displayName
    const bio = profile.querySelector('#bio-input').value.trim();
    const disName = profile.querySelector('#profile-name').innerText.trim();

    // Initialize an object to hold any updates
    const requestObject = {};

    // Check and add updates to the requestObject if necessary
    if (bio && bio !== userInfo.bio) {
        requestObject.bio = bio;
        profile.querySelector('.save-btn').style.display = 'none';
    }
    if (disName && disName !== userInfo.name) {
        requestObject.displayName = disName;
    }
    if (Object.keys(requestObject).length === 0) {
        return;
    }


    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
        const response = await fetch(`/req/profile/update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(requestObject)
        });
        const data = await response.json();

        if (data.success) {
            console.log('User information Updated Successfully');

        } else {
            console.error('User information Update not successfull');
        }
    } catch (error) {
        console.error('Failed to update user information to server!');
    }
}
