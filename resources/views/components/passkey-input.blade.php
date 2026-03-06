{{-- 
    Reusable Passkey Input Component
    Used in both registration and handshake pages
    
    Props:
    - $id: HTML id attribute for the input
    - $placeholder: Placeholder text
    - $wrapperClass: Additional CSS classes for the wrapper div
    - $name: Name attribute for the input (optional, defaults to 'not_a_password_input')
--}}

<div class="password-input-wrapper {{ $wrapperClass ?? '' }}">
    <input
        class="passkey-input"
        placeholder="{{ $placeholder }}"
        id="{{ $id }}"
        type="text"
        autocomplete="new-password"
        autocorrect="off"
        autocapitalize="off"
        spellcheck="false"
        name="{{ $name ?? 'not_a_password_input' }}"
    />
    <div class="btn-xs" id="visibility-toggle">
        <x-icon name="eye" id="eye"/>
        <x-icon name="eye-off" id="eye-off" style="display: none"/>
    </div>
</div>
