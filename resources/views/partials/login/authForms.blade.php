<div id="main-auth-panel">
    @if($showLoginForm)
        <form class="form-column" id="hawkiLoginForm">
            @csrf
            <label for="account">{{ $translation["username"] }}</label>
            <input type="text" name="account" id="account" onkeypress="onLoginKeydown(event)">
            <label for="password">{{ $translation["password"] }}</label>
            <input type="password" name="password" id="password" onkeypress="onLoginKeydown(event)">
        </form>
        <div id="login-Button-panel">
            <div id="login-message"></div>
            <button id="loginButton" class="btn-lg-fill align-end top-gap-1" type="button"
                    onclick="submitLogin()">{{ $translation['Login'] }}</button>
        </div>

        {{-- Guest Access Request Link --}}
        @if($localSelfserviceActive ?? false)
            <div class="guest-access-request">
                <button class="btn-link" onclick="switchToGuestRequestForm()">
                    {{ $translation['request_guest_access'] ?? 'Request Guest Access' }}
                </button>
            </div>
        @endif
    @else
        <form class="form-column" method="post" id="hawkiLoginForm" action="/req/login">
            @csrf
            @if($errors->has('login_error'))
                <div id="login-message">
                    {{ $errors->first('login_error') }}
                </div>
            @endif
            <button id="loginButton" class="btn-lg-fill align-end top-gap-1" type="submit"
                    name="submit">{{ $translation['Login'] }}</button>
        </form>

        {{-- Local Users Link (only show link when enabled) --}}
        @if(($localUsersActive ?? false))
            <div class="local-users-link">
                <button class="btn-link" onclick="switchToLocalUsersLogin()">
                    {{ $translation['local_users_login'] ?? 'Local Users Login' }}
                </button>
            </div>
        @endif
    @endif
</div>

{{-- Local Users Login Panel (hidden by default) - only show if we do not have a login form --}}
@if(!$showLoginForm && ($localUsersActive ?? false))
    <div id="local-auth-panel" style="display: none;">
        <form class="form-column" id="loginForm-LOCAL">
            @csrf
            <h1 class="login-form-title">{{ $translation['login_title'] ?? $translation['Login'] ?? 'Login' }}</h1>
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

        @if($localSelfserviceActive ?? false)
            <div class="guest-access-request">
                <button class="btn-link" onclick="switchToGuestRequestForm()">
                    {{ $translation['request_guest_access'] ?? 'Request Guest Access' }}
                </button>
            </div>
        @endif

        <div class="back-to-main-link">
            <button class="btn-link" onclick="switchToMainLogin()">
                {{ $translation['back_to_main_login'] ?? 'Back to Main Login' }}
            </button>
        </div>
    </div>
@endif

{{-- Guest Access Request Form (hidden by default) --}}
@if(($localUsersActive ?? false) && ($localSelfserviceActive ?? false))
    <div id="guest-request-panel" style="display: none;">
        <form class="form-column" id="guestRequestForm">
            @csrf
            <h3>{{ $translation["request_guest_access"] ?? "Request Guest Access" }}</h3>

            <label for="request-username">{{ $translation["guest_username"] ?? "Username" }} *</label>
            <input type="text" name="username" id="request-username" required>
            <div id="username-error" class="error-message"></div>

            <label for="request-password">{{ $translation["guest_password"] ?? "Password" }} *</label>
            <input type="password" name="password" id="request-password" required>
            <div id="password-error" class="error-message"></div>

            <label for="request-password-confirm">{{ $translation["confirm_password"] ?? "Confirm Password" }} *</label>
            <input type="password" name="password_confirmation" id="request-password-confirm" required>
            <div id="password-confirm-error" class="error-message"></div>

            <label for="request-email">{{ $translation["email"] ?? "Email" }} *</label>
            <input type="email" name="email" id="request-email" required>
            <div id="email-error" class="error-message"></div>

            <label for="request-employeetype">{{ $translation["user_group"] ?? "User Group" }} *</label>
            <select name="employeetype" id="request-employeetype" required>
                <option value="">{{ $translation["select_user_group"] ?? "Select User Group" }}</option>
                @if(isset($availableRoles))
                    @foreach($availableRoles as $role)
                        <option value="{{ $role->slug }}">{{ $role->name }}</option>
                    @endforeach
                @endif
            </select>
            <div id="employeetype-error" class="error-message"></div>
        </form>

        <div id="guest-request-Button-panel">
            <div id="guest-request-message"
                 data-submitting="{{ $translation['submitting'] ?? 'Submitting...' }}"
                 data-submitting-text="{{ $translation['submitting_text'] ?? 'Please wait while we process your request.' }}"
                 data-success="{{ $translation['success'] ?? 'Success!' }}"
                 data-success-message="{{ $translation['success_message'] ?? 'Your guest access request has been submitted successfully. You can now log in with your credentials.' }}"
                 data-error="{{ $translation['error'] ?? 'Error:' }}"
                 data-network-error="{{ $translation['network_error'] ?? 'A network error occurred. Please check your connection and try again.' }}"
                 data-general-error="{{ $translation['general_error'] ?? 'An error occurred while processing your request. Please try again.' }}">
            </div>
            <button id="submitGuestRequestButton" class="btn-lg-fill align-end top-gap-1" type="button" onclick="submitGuestRequest()">
                {{ $translation['submit_request'] ?? 'Submit Request' }}
            </button>
        </div>

        @if($showLoginForm)
            {{-- In LOCAL_ONLY mode, go back to main (which is local login) --}}
            <div class="back-to-main-link">
                <button class="btn-link" onclick="switchToMainLogin()">
                    {{ $translation['back_to_login'] ?? 'Back to Login' }}
                </button>
            </div>
        @else
            {{-- In other modes, go back to local auth panel --}}
            <div class="back-to-local-link">
                <button class="btn-link" onclick="switchToLocalUsersLogin()">
                    {{ $translation['back_to_login'] ?? 'Back to Login' }}
                </button>
            </div>
        @endif
    </div>
@endif
