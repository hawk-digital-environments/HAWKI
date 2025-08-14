{{-- Main Authentication Methods --}}
<div id="main-auth-panel">
    @if($authenticationMethod === 'OIDC')
        <form class="form-column" method="post" id="loginForm-OIDC" action="/req/login-oidc">
            @csrf
            <button id="loginButton" class="btn-lg align-end top-gap-1">{{ $translation['Login'] }}</button>
        </form>
    @elseif($authenticationMethod === 'LDAP' || $authenticationMethod === 'TestAuth')
        <form class="form-column" id="loginForm-LDAP">
            @csrf
            <label for="account">{{ $translation["username"] }}</label>
            <input type="text" name="account" id="account" onkeypress="onLoginKeydown(event)">
            <label for="password">{{ $translation["password"] }}</label>
            <input type="password" name="password" id="password" onkeypress="onLoginKeydown(event)">
        </form>
        <div id="login-Button-panel">
            <div id="login-message"></div>
            <button id="loginButton" class="btn-lg-fill align-end top-gap-1" type="button" onclick="LoginLDAP()">{{ $translation['Login'] }}</button>
        </div>
    @elseif($authenticationMethod === 'Shibboleth')
        <form class="form-column" method="post" id="loginForm-Shib" action="/req/login-shibboleth">
            @csrf
            <button id="loginButton" class="btn-lg-fill align-end top-gap-1" type="submit" name="submit">{{ $translation['Login'] }}</button >
        </form>
    @else
        No authentication method defined
    @endif

    {{-- Local Users Link (only show link when enabled) --}}
    @if($localUsersActive ?? false)
        <div class="separator">
            <span>{{ $translation['or'] ?? 'or' }}</span>
        </div>
        
        <div class="local-users-link">
            <button class="btn-link" onclick="switchToLocalUsersLogin()">
                {{ $translation['local_users_login'] ?? 'Local Users Login' }}
            </button>
        </div>
    @endif
</div>

{{-- Local Users Login Panel (hidden by default) --}}
@if($localUsersActive ?? false)
    <div id="local-auth-panel" style="display: none;">
        <form class="form-column" id="loginForm-LOCAL">
            @csrf
            <label for="guest-account">{{ $translation["guest_username"] ?? "Username" }}</label>
            <input type="text" name="account" id="guest-account" onkeypress="onGuestLoginKeydown(event)">
            <label for="guest-password">{{ $translation["guest_password"] ?? "Password" }}</label>
            <input type="password" name="password" id="guest-password" onkeypress="onGuestLoginKeydown(event)">
        </form>
        
        <div id="guest-login-Button-panel">
            <div id="guest-login-message"></div>
            <button id="guestLoginButton" class="btn-lg-fill align-end top-gap-1" type="button" onclick="LoginLocal()">
                {{ $translation['local_login'] ?? 'Login' }}
            </button>
        </div>
        
        <div class="separator">
            <span>{{ $translation['or'] ?? 'or' }}</span>
        </div>
        
        <div class="back-to-main-link">
            <button class="btn-link" onclick="switchToMainLogin()">
                {{ $translation['back_to_main_login'] ?? 'Back to Main Login' }}
            </button>
        </div>
    </div>
@endif
