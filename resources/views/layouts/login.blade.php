<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
	<meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <link rel="icon" type="image/png" href="{{ route('system.image', 'favicon') }}">
    <link rel="apple-touch-icon" href="{{ route('system.image', 'favicon') }}">


    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/login_style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/settings_style.css') }}">
    {{-- Insert stylesheet from database --}}
    <link rel="stylesheet" href="{{ route('css.get', 'custom-styles') }}">


    <script src="{{ asset('js_v2.0.1_f1/functions.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/settings_functions.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/guest_request_functions.js') }}"></script>

    {!! $settingsPanel !!}

    <script>
		InitializePreDomSettings(false);
        UpdateSettingsLanguage('{{ Session::get("language")['id'] }}');
	</script>
    
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <div>
            <div class="logo"></div>
        </div>
        <div class="loginPanel">
			{!! $authForms !!}
        </div>


        <div class="footerPanel">
  
            <button class="btn-sm" onclick="toggleSettingsPanel(true)">
                <x-icon name="settings-icon"/>
            </button>
            <div class="impressumPanel">
                <a href="/dataprotection" target="_blank">{{ $translation["DataProtection"] }}</a>
                <a href="{{ env("IMPRINT_LOCATION") }}" target="_blank">{{ $translation["Impressum"] }}</a>
            </div>
        </div>

    </div>

    <main>
        <div class="backgroundImageContainer">
            <video class="image_preview_container" src="" type="video/m4v" preload="none" autoplay loop muted></video>
            <a href="" target="_blank" class="video-credits"></a>
        </div>
    </main>
</div>

@include('partials.overlay')

</body>
</html>

<script>
    window.addEventListener('DOMContentLoaded', (event) => {
        if(window.innerWidth < 480){
            const bgVideo = document.querySelector(".image_preview_container");
            bgVideo.remove();
        }

        // console.log(@json($activeOverlay));
        setTimeout(() => {
            if(@json($activeOverlay)){
                // console.log('close overlay');
                setOverlay(false, true)
            }
        }, 100);
    });

    function onLoginKeydown(event){
        if(event.key == "Enter"){
            const username = document.getElementById('account');
            // console.log(username.value);
            if(!username.value){
                return;
            }
            const password = document.getElementById('password');
            if(document.activeElement != password){
                password.focus();
                return;
            }
            if(username.value && password.value){
                LoginLDAP();
            }
        }
    }

    function onGuestLoginKeydown(event){
        if(event.key == "Enter"){
            const username = document.getElementById('guest-account');
            if(!username.value){
                return;
            }
            const password = document.getElementById('guest-password');
            if(document.activeElement != password){
                password.focus();
                return;
            }
            if(username.value && password.value){
                LoginLocal();
            }
        }
    }
    async function LoginLDAP() {
        try {
            var formData = new FormData();
            formData.append("account", document.getElementById("account").value);
            formData.append("password", document.getElementById("password").value);
            const csrfToken = document.getElementById('loginForm-LDAP').querySelector('input[name="_token"]').value;

            const response = await fetch('/req/login-ldap', {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": csrfToken
                },
                body: formData
            });

            if (!response.ok) {
                throw new Error("Login request failed");
            }

            const data = await response.json();

            if (data.success) {
                await setOverlay(true, true)
                window.location.href = data.redirectUri;

            } else {
                // console.log('login failed');
                document.getElementById("login-message").textContent = 'Login Failed!';
            }
        } catch (error) {
            console.error(error);
        }
    }

    async function LoginLocal() {
        try {
            var formData = new FormData();
            formData.append("account", document.getElementById("guest-account").value);
            formData.append("password", document.getElementById("guest-password").value);
            const csrfToken = document.getElementById('loginForm-LOCAL').querySelector('input[name="_token"]').value;

            const response = await fetch('/req/login-local', {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": csrfToken
                },
                body: formData
            });

            if (!response.ok) {
                throw new Error("Guest login request failed");
            }

            const data = await response.json();

            if (data.success) {
                await setOverlay(true, true)
                window.location.href = data.redirectUri;

            } else {
                // console.log('guest login failed');
                document.getElementById("guest-login-message").textContent = data.message || 'Guest Login Failed!';
            }
        } catch (error) {
            console.error(error);
            document.getElementById("guest-login-message").textContent = 'Guest Login Error!';
        }
    }

    async function LoginLocalMain() {
        try {
            var formData = new FormData();
            formData.append("account", document.getElementById("main-local-account").value);
            formData.append("password", document.getElementById("main-local-password").value);
            const csrfToken = document.getElementById('loginForm-LOCAL-MAIN').querySelector('input[name="_token"]').value;

            const response = await fetch('/req/login-local', {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": csrfToken
                },
                body: formData
            });

            if (!response.ok) {
                throw new Error("Local login request failed");
            }

            const data = await response.json();

            if (data.success) {
                await setOverlay(true, true)
                window.location.href = data.redirectUri;

            } else {
                // console.log('local login failed');
                document.getElementById("main-local-login-message").textContent = data.message || 'Login Failed!';
            }
        } catch (error) {
            console.error(error);
            document.getElementById("main-local-login-message").textContent = 'Login Error!';
        }
    }

    function onLocalMainLoginKeydown(event){
        if(event.key == "Enter"){
            const username = document.getElementById('main-local-account');
            if(!username.value){
                return;
            }
            const password = document.getElementById('main-local-password');
            if(document.activeElement != password){
                password.focus();
                return;
            }
            if(username.value && password.value){
                LoginLocalMain();
            }
        }
    }

    function switchToLocalUsersLogin() {
        // Use the guest request function if available, otherwise fall back to manual handling
        if (typeof resetAllAuthPanels === 'function') {
            resetAllAuthPanels();
            document.getElementById('local-auth-panel').style.display = 'block';
        } else {
            // Fallback for cases where guest_request_functions.js is not loaded
            hideAllAuthPanelsManual();
            document.getElementById('local-auth-panel').style.display = 'block';
        }
        // Focus on username field
        const guestAccountField = document.getElementById('guest-account');
        if (guestAccountField) {
            guestAccountField.focus();
        }
    }

    function switchToMainLogin() {
        // Use the guest request function if available, otherwise fall back to manual handling
        if (typeof resetAllAuthPanels === 'function') {
            resetAllAuthPanels();
            document.getElementById('main-auth-panel').style.display = 'block';
        } else {
            // Fallback for cases where guest_request_functions.js is not loaded
            hideAllAuthPanelsManual();
            document.getElementById('main-auth-panel').style.display = 'block';
        }
        // Focus on main login field if it exists
        const mainLoginField = document.getElementById('account');
        if (mainLoginField) {
            mainLoginField.focus();
        }
    }

    // Fallback function for manual panel hiding
    function hideAllAuthPanelsManual() {
        const panels = ['main-auth-panel', 'local-auth-panel', 'guest-request-panel'];
        panels.forEach(panelId => {
            const panel = document.getElementById(panelId);
            if (panel) {
                panel.style.display = 'none';
            }
        });
    }

    function requestGuestAccess() {
        // This function is now replaced by switchToGuestRequestForm()
        // Kept for backward compatibility
        switchToGuestRequestForm();
    }
</script>