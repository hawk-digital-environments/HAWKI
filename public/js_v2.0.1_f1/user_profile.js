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


function selectProfileAvatar(btn, upload = false){

    const imageElement = btn.querySelector('.selectable-image');
    const initials = btn.querySelector('.user-inits');

    openImageSelection(imageElement.getAttribute('src'), function(croppedImage) {
        imageElement.style.display = 'block';
        if(initials){
            initials.style.display = 'none';
        }

        imageElement.setAttribute('src', croppedImage);
        if(upload){
            uploadProfileAvatar(croppedImage);
        }
    });
}

async function uploadProfileAvatar(imgBase64){
    
    const url = `/req/profile/update`;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({'img':imgBase64})
        });
        const data = await response.json();

        if (data.success) {
            console.log('Image Uploaded Successfully');
            
        } else {
            console.error('Upload not successfull');
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
                'X-CSRF-TOKEN': csrfToken
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