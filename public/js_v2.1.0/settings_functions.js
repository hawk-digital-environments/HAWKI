


function InitializePreDomSettings(){
    SwitchDarkMode(false);
}


document.addEventListener('DOMContentLoaded', function() {
    // Changing the language will refresh the page.
    // check if settings was open before changing language to prevent closing the settings menu.
    const settingsStatus = getLSwithExpiry('settingsPanelOpen');

    if (settingsStatus === 'true') {
        toggleSettingsPanel(true);
    }

    //DOUBLE CHECK DARK MODE AFTER THE DOM IS LOADED.
    setupLoginBackgroud();

});



/// Change Darkmode toggle state in settings panel.
function toggleSettingsPanel(activation){
    const settingsModal = document.querySelector('.settings-modal');
    let settingToggleValue = '';
    if(activation == true){
        settingsModal.style.display = 'flex';
        settingToggleValue = 'true';
        fetchGuidelines();
    }
    else{
        settingsModal.style.display = 'none';
        settingToggleValue = 'false';
        ToggleSettingsContent('aboutHAWKI', false);
        ToggleSettingsContent('guideline', false);
    }
}

async function fetchGuidelines(){
            // Assume fetchLatestPolicy() returns an object {view, announcement}
        const {view, announcement} = await fetchLatestPolicy();

        // Render the HTML (MD rendered to HTML string)
        const renderedHtml = md.render(view, false);

        // const parent = document.querySelector('')
        // Insert the rendered HTML into the designated container
        const settingsModal = document.querySelector('.settings-modal');
        const policyContentBox = settingsModal.querySelector('#policy-content');
        policyContentBox.innerHTML = renderedHtml;

        // Add target and rel attributes for external links (XSS-protection best practice)
        policyContentBox.querySelectorAll('a').forEach(a => {
            a.setAttribute('target', '_blank');
            a.setAttribute("rel", "noopener noreferrer");
        });
        policyContentBox.querySelector('h1').style.textAlign ='center';
}

/// Toggle about us in the settings panel.
function ToggleSettingsContent(content, activation){
    const panel = document.querySelector('.settings-panel');

    switch(content){
        case "aboutHAWKI":
            if(activation == true){
                panel.classList.add('aboutActive');
            }
            else{
                panel.classList.remove('aboutActive');
            }
        break;
        case "guideline":
            if(activation == true){
                panel.classList.add('guidelineActive');
            }
            else{
                panel.classList.remove('guidelineActive');
            }
        break;
    }
}

/// Switching between dark/light mode.
/// isSet checks if the user has set dark mode. if not it only initializes the theme when the page is being loaded.
let darkMode = "disabled"
function SwitchDarkMode(isSet){
    const root = document.querySelector(':root');
    const icon = document.querySelector('#darkMode-switch-icon');
    const tog = document.querySelector('.darkMode-switch');

    // If user has changed the theme mode.
    if(isSet){
        root.style.setProperty('--color-transition-duration', '200ms');
        if(localStorage.getItem('darkMode') === "enabled"){
            localStorage.setItem("darkMode", "disabled");
        }
        else{
            localStorage.setItem("darkMode", "enabled");
        }
    }
    else{
        //if local storage is not set (first time user...)
        if(localStorage.getItem('darkMode') === null){
            // console.log('dark mode not in local storage.');
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                // dark mode
                localStorage.setItem("darkMode", "enabled");
            }
            else{
                localStorage.setItem("darkMode", "disabled");
            }
        }
        root.style.setProperty('--color-transition-duration', '0ms');
    }

    darkMode = localStorage.getItem("darkMode");



    if(darkMode === 'enabled'){
        document.documentElement.className = 'darkMode';
        icon.setAttribute('src', '/img/moon.svg');
        tog.classList.add('active');
    }
    else{
        document.documentElement.className = 'lightMode';
        icon.setAttribute('src', '/img/sun.svg');
        tog.classList.remove('active');
    }
    setupLoginBackgroud();
}

async function setupLoginBackgroud(){
    const loginBg = document.querySelector('.image_preview_container');
    const loginBgCredit = document.querySelector('.video-credits')

    const videosUrl = '../bg_videos'
    let videosIndex;

    await fetch(`${videosUrl}/bg_videos.json`)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        videosIndex = data;
    })
    .catch(error => console.error("Error fetching JSON:", error));

    if(darkMode === 'enabled'){
        if(loginBg != null){

            let fileIndex = Math.floor(Math.random() * videosIndex.darkmode.length);
            if(localStorage.getItem('lbgd')){
                fileIndex = (Number(localStorage.getItem('lbgd')) + 1) % videosIndex.darkmode.length;
            }
            const bgObj = videosIndex.darkmode[fileIndex];
            localStorage.setItem('lbgd', fileIndex);

            loginBg.setAttribute('src', `${videosUrl}/${bgObj.file}`);
            loginBgCredit.innerText = `By ${bgObj.creator}`;
            loginBgCredit.setAttribute('href', bgObj.link);
        }
    }
    else{
        if(loginBg != null){
            let fileIndex = Math.floor(Math.random() * videosIndex.lightmode.length);
            if(localStorage.getItem('lbgl')){
                fileIndex = (Number(localStorage.getItem('lbgl')) + 1) % videosIndex.lightmode.length;
            }
            const bgObj = videosIndex.lightmode[fileIndex];
            localStorage.setItem('lbgl', fileIndex);

            loginBg.setAttribute('src', `${videosUrl}/${bgObj.file}`);
            loginBgCredit.innerText = `By ${bgObj.creator}`;
            loginBgCredit.setAttribute('href', bgObj.link);
        }
    }
}



/// Change active language button in settings panel.
function UpdateSettingsLanguage(lang){
    const btn = document.getElementById(lang + '_btn');
    btn.classList.add('accentText');
    btn.style.fontWeight= '700';
}





/// Get and Set local storage variable with expiry time.
function setLSwithExpiry(key, value, expiry){
    const now = new Date()
    const jsonVar = {
        value: value,
        expiry: now.getTime() + expiry,
    }
    localStorage.setItem(key, JSON.stringify(jsonVar))
}


function getLSwithExpiry(key) {
	const itemStr = localStorage.getItem(key)
	if (!itemStr) {
		return null
	}
	const item = JSON.parse(itemStr)
	const now = new Date()
	if (now.getTime() > item.expiry) {
		localStorage.removeItem(key)
		return null
	}
	return item.value
}



/// Remove User Data on Local Storage and Logout
async function clearPersonalData(){

    const confirmed = await openModal(ModalType.CONFIRM, translation.Cnf_passkeyRemove);
    if (!confirmed) {
        return;
    }

    cleanupUserData(()=>{
        // console.log('cleaned Up previous user data.');
        logout();
    });
}


/// Language


function changeLanguage(lang) {
    // Changing the language will refresh the page.
    // check if settings was open before changing language to prevent closing the settings menu.
    setLSwithExpiry('settingsPanelOpen', 'true', 1000);

    // Retrieve CSRF token from meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    // console.log(lang);
    fetch('/req/changeLanguage', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({inputLang: lang}),
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error changing language');
        }
        return response.json(); // Read the response body as text
    })
    .then(data => {

        // console.log(data);
        location.reload(); // Reload the page if language change is successful
    })
    .catch(error => {
        console.error(error);
    });
}
