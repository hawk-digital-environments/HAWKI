<?php
?>
<div class="settings-modal"> 
    <div class="settings-panel">
        <div class="settings-wrapper">
            <div class="settings-content">
                <h1><?php echo $translation["settings"]; ?></h1>
                <h3 id="swtichContent-btn" class="">
                    <div href="#" onclick="ToggleSettingsContent('aboutHAWKI',true)">
                        <?php echo $translation["AboutHAWKI"]; ?>
                    </div>
                    <svg viewBox="0 0 25 25">
                        <g class="button-path-stroke-color" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M 12 16 l 4 -4 l -4 -4 M 8 12 H 16"/>
                        </g>
                    </svg>
                </h3>
                <h3 id="swtichContent-btn" class="">
                    <div href="#" onclick="ToggleSettingsContent('guideline',true)">
                        <?php echo $translation["guideline_Title"]; ?>
                    </div>
                    <svg viewBox="0 0 25 25">
                        <g class="button-path-stroke-color" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M 12 16 l 4 -4 l -4 -4 M 8 12 H 16"/>
                        </g>
                    </svg>
                </h3>
                <div class="settings-section">
                    <h3><?php echo $translation["language"]; ?></h3>
                    <div class="language-selection">
                        <a class="language-btn" onclick="changeLanguage('de_DE')" id="de_DE_btn">
                            DE
                        </a>
                        <a class="language-btn" onclick="changeLanguage('en_US')" id="en_US_btn">
                            EN
                        </a>
                        <a class="language-btn" onclick="changeLanguage('es_ES')" id="es_ES_btn">
                            ES
                        </a>
                        <a class="language-btn" onclick="changeLanguage('fr_FR')" id="fr_FR_btn">
                            FR
                        </a>
                        <a class="language-btn" onclick="changeLanguage('it_IT')" id="it_IT_btn">
                            IT
                        </a>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3><?php echo $translation["theme"]; ?></h3>
                    <div class="darkMode-switch-panel">
                        <div class="darkMode-switch">
                            <div class="toggle-area"  onclick="SwitchDarkMode(true)">
                                <div id="theme-toggle">
                                    <img id="darkMode-switch-icon" src="public/img/moon.svg" alt="HAWK Logo" width="20px">
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>

            <?php
                include('about.php');
            ?>

            <div class="guideline-content">
                <div class="content-header">
                    <div class="back-btn" onclick="ToggleSettingsContent('guideline',false)">
                        <svg viewBox="0 0 25 25" width="50" height="50">
                            <g class="button-path-stroke-color" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M 12 8 l -4 4 l 4 4 M 16 12 H 8"/>
                            </g>
                        </svg>
                    </div>
                    <h1><?php echo $translation["guideline_Title"]; ?></h1>
                </div>
                <div class="content-text-container">
                    <?php echo $translation["usage_guideline"]; ?><br><br><br>
                </div>

            </div>


            <div class="settings-closeButton" onclick="toggleSettingsPanel(false)">
                <svg viewBox="0 0 100 100"><path class="button-path-fill-color" d="M 19.52 19.52 a 6.4 6.4 90 0 1 9.0496 0 L 51.2 42.1504 L 73.8304 19.52 a 6.4 6.4 90 0 1 9.0496 9.0496 L 60.2496 51.2 L 82.88 73.8304 a 6.4 6.4 90 0 1 -9.0496 9.0496 L 51.2 60.2496 L 28.5696 82.88 a 6.4 6.4 90 0 1 -9.0496 -9.0496 L 42.1504 51.2 L 19.52 28.5696 a 6.4 6.4 90 0 1 0 -9.0496 z"/></svg>
            </div>
        </div>
        <div class="betaMessage">
			<?php echo $translation["BetaMessage"]; ?>
		</div>
    </div>
</div>


<script>

    function changeLanguage(lang) {
        // Changing the language will refresh the page.
        // check if settings was open before changing language to prevent closing the settings menu.
        setLSwithExpiry('settingsPanelOpen', 'true', 1000);

        // Retrieve CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const languageController = "<?php LIBRARY_PATH . 'language_controller.php'?>"

        // fetch to change language file
        fetch(languageController, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken // Add CSRF token header
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

            document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.csrf_token);
            location.reload(); // Reload the page if language change is successful
        })
        .catch(error => {
            console.error(error);
        });
    }

</script>