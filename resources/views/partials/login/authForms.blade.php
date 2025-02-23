@if($authenticationMethod === 'OIDC')
    <form action="/oidc_login" class="form-column" method="post" id="loginForm-OIDC" action="/req/login-oidc">
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
