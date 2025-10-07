<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
	<meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <link rel="icon" type="image/png" href="{{ route('system.image', 'favicon') }}">

    <link rel="stylesheet" href="{{ asset('css_v2.1.0/gfont-firesans/firesans.css') }}">
    <link rel="stylesheet" href="{{ route('css.get', 'style') }}">
    <link rel="stylesheet" href="{{ route('css.get', 'login_style') }}">
    <link rel="stylesheet" href="{{ route('css.get', 'settings_style') }}">
    <link rel="stylesheet" href="{{ route('css.get', 'custom-styles') }}">

    <script src="{{ asset('js_v2.1.0/functions.js') }}"></script>
    <script src="{{ asset('js_v2.1.0/settings_functions.js') }}"></script>
    <script src="{{ asset('js_v2.1.0/guest_request_functions.js') }}"></script>

    {!! $settingsPanel !!}

    <script>
		InitializePreDomSettings(false);
        UpdateSettingsLanguage('{{ Session::get("language")['id'] }}');
	</script>

</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <div class="logo"></div>

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
    window.addEventListener('DOMContentLoaded', () => {
        if(window.innerWidth < 480){
            const bgVideo = document.querySelector(".image_preview_container");
            bgVideo.remove();
        }

        setTimeout(() => {
            if(@json($activeOverlay)){
                // console.log('close overlay');
                setOverlay(false, true)
            }
        }, 100);
    });

    function onLoginKeydown(event){
        if(event.key === "Enter"){
            const username = document.getElementById('account');
            // console.log(username.value);
            if(!username.value){
                return;
            }
            const password = document.getElementById('password');
            if(document.activeElement !== password){
                password.focus();
                return;
            }
            if(username.value && password.value){
                LoginLDAP();
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
                    "X-CSRF-TOKEN": csrfToken,
                    'Accept': 'application/json',
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

    // Local authentication keydown handler
    function onLocalLoginKeydown(event){
        if(event.key === "Enter"){
            const username = document.getElementById('account');
            if(!username.value){
                return;
            }
            const password = document.getElementById('password');
            if(document.activeElement !== password){
                password.focus();
                return;
            }
            if(username.value && password.value){
                LoginLocal();
            }
        }
    }

    // Guest login keydown handler  
    function onGuestLoginKeydown(event){
        if(event.key === "Enter"){
            const username = document.getElementById('guest-account');
            if(!username.value){
                return;
            }
            const password = document.getElementById('guest-password');
            if(document.activeElement !== password){
                password.focus();
                return;
            }
            if(username.value && password.value){
                LoginLocal();
            }
        }
    }

    // Local login function
    async function LoginLocal() {
        try {
            var formData = new FormData();
            
            // Check if we're in guest mode or main mode
            const isGuestMode = document.getElementById('local-auth-panel') && 
                               document.getElementById('local-auth-panel').style.display !== 'none';
            
            const accountField = isGuestMode ? 'guest-account' : 'account';
            const passwordField = isGuestMode ? 'guest-password' : 'password';
            const messageField = isGuestMode ? 'guest-login-message' : 'login-message';
            
            formData.append("account", document.getElementById(accountField).value);
            formData.append("password", document.getElementById(passwordField).value);
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const response = await fetch('/req/login-local', {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": csrfToken,
                    'Accept': 'application/json',
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
                document.getElementById(messageField).textContent = 'Login Failed!';
            }
        } catch (error) {
            console.error(error);
            const messageField = document.getElementById('local-auth-panel') && 
                               document.getElementById('local-auth-panel').style.display !== 'none' ? 
                               'guest-login-message' : 'login-message';
            document.getElementById(messageField).textContent = 'Login Failed!';
        }
    }

</script>
