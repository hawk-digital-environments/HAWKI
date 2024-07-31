document.addEventListener('DOMContentLoaded', function() {
    // Changing the language will refresh the page.
    // check if settings was open before changing language to prevent closing the settings menu.
    const settingsStatus = getLSwithExpiry('settingsPanelOpen');

    if (settingsStatus === 'true') {
        toggleSettingsPanel(true);
    }
    
    //DOUBLE CHECK DARK MODE AFTER THE DOM IS LOADED.
    SwitchDarkMode(false);
});



//#region SHARED SCRIPTS BETWEEN PAGES
function submenu(element){
    if(element.classList.contains('active')){
        element.classList.remove("active");
        element.nextElementSibling.classList.remove("active");
    }else{
        document.querySelector(".menu-item.active")?.classList.remove("active");
        document.querySelector(".submenu.active")?.classList.remove("active");
        document.querySelector(".menu-item.open")?.classList.remove("open");
        element.classList.add("active");
        element.nextElementSibling.classList.add("active");
    }
}

	
function modalClick(button){
    const modal = button.closest('.modal');
    localStorage.setItem(modal.id, "true")
    modal.remove();
}

function CheckModals(){
    const modals = document.querySelectorAll('.modal');
    for(let i = 0; i < modals.length; i++){
        const modal = modals[i];
        if(localStorage.getItem(modal.id) === 'true'){
            modal.remove();
        }
    }
}


function copyToInput(selector) {
    const originalText = document.querySelector(selector).textContent;
    const cleanedText = originalText.split('\n')  // Split text by new lines
                                .map(line => line.trim())  // Remove leading and trailing spaces from each line
                                .filter(line => line !== '')  // Filter out empty lines
                                .join(' ');  // Join lines back together with a single space

    document.querySelector(".input-field").value = cleanedText;
    resize(document.querySelector(".input-field"));
}

function resize(element) {
    element.style.height = 'auto';
    element.style.height = element.scrollHeight + "px";
    element.scrollTop = element.scrollHeight;
    element.scrollTo(element.scrollTop, (element.scrollTop + element.scrollHeight));
}


// Switching between dark/light mode.
// isSet checks if the user has set dark mode. if not it only initializes the theme when the page is being loaded.
let darkMode = "disabled"
function SwitchDarkMode(isSet){
    const root = document.querySelector(':root');
    const icon = document.querySelector('#darkMode-switch-icon');
    const hljsTheme = document.getElementById('hljsTheme'); 
    const loginBg = document.querySelector('.image_preview_container');
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
            console.log('dark mode not in local storage.');
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
        icon.setAttribute('src', 'public/img/moon.svg');
        if(hljsTheme != null) hljsTheme.setAttribute('href', "public/style/hljsDark.css");
        
        if(loginBg != null){
            loginBg.style.opacity = "1";
            loginBg.setAttribute('src', 'public/img/HAWKIBG-DM.m4v');
        }
        tog.classList.add('active');
    }
    else{
        document.documentElement.className = 'lightMode';
        icon.setAttribute('src', 'public/img/sun.svg');
        if(hljsTheme != null) hljsTheme.setAttribute('href', "public/style/hljsLight.css");
        
        if(loginBg != null){
            loginBg.style.opacity = ".5";
            loginBg.setAttribute('src', 'public/img/HAWKIBG.m4v');
        }
        tog.classList.remove('active');
    }
}
//#endregion

// Change active language button in settings panel.
function UpdateSettingsLanguage(lang){
    const btn = document.getElementById(lang + '_btn');
    btn.classList.add('accentText');
    btn.style.fontWeight= '700';
}

// Change Darkmode toggle state in settings panel.
function toggleSettingsPanel(activation){
    const settingsModal = document.querySelector('.settings-modal');
    let settingToggleValue = '';
    if(activation == true){
        settingsModal.style.display = 'flex';
        settingToggleValue = 'true';
    }
    else{
        settingsModal.style.display = 'none';
        settingToggleValue = 'false';
        ToggleSettingsContent('aboutHAWKI', false);
        ToggleSettingsContent('guideline', false);

    }
}

// Toggle about us in the settings panel.
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




// Get and Set local storage variable with expiry time.
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


