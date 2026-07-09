@if($showLoginForm)
    <form class="form-column" id="hawkiLoginForm">
        @csrf
        <label for="account">{{ __("username") }}</label>
        <input type="text" name="account" id="account" onkeypress="onLoginKeydown(event)">
        <label for="password">{{ __("password") }}</label>
        <input type="password" name="password" id="password" onkeypress="onLoginKeydown(event)">
    </form>
    <div id="login-Button-panel">
        <div id="login-message"></div>
        <button id="loginButton" class="btn-lg-fill align-end top-gap-1" type="button"
                onclick="submitLogin()">{{ __('Login') }}</button>
    </div>
@else
    <form class="form-column" method="post" id="hawkiLoginForm" action="/req/login">
        @csrf
        @if($errors->has('login_error'))
            <div id="login-message">
                {{ $errors->first('login_error') }}
            </div>
        @endif
        <button id="loginButton" class="btn-lg-fill align-end top-gap-1" type="submit"
                name="submit">{{ __('Login') }}</button>
    </form>
@endif
