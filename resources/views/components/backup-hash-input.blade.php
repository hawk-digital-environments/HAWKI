{{-- 
    Reusable Backup Hash Input Component
    Used in both registration and handshake pages for backup recovery codes
    
    Props:
    - $id: HTML id attribute for the input
    - $placeholder: Placeholder text
    - $wrapperClass: Additional CSS classes for the wrapper div (optional)
    - $includeUploadButton: Whether to include upload button (optional, defaults to false)
    - $uploadOnClick: JavaScript function to call when upload button is clicked (optional)
    - $autocomplete: Autocomplete attribute value (optional, defaults to 'new-password')
--}}

<div class="backup-hash-row {{ $wrapperClass ?? '' }}">
    <div class="password-input-wrapper">
        <input 
            id="{{ $id }}" 
            name="password"
            type="password"
            autocomplete="{{ $autocomplete ?? 'new-password' }}"
            placeholder="{{ $placeholder }}"
            class="backup-hash-input"
        />
        <div class="btn-xs backup-visibility-toggle" data-target="{{ $id }}">
            <x-icon name="eye" class="eye-icon"/>
            <x-icon name="eye-off" class="eye-off-icon" style="display: none;"/>
        </div>
    </div>
    @if($includeUploadButton ?? false)
    <button type="button" class="btn-sm border" onclick="{{ $uploadOnClick ?? 'uploadTextFile()' }}">
        <x-icon name="upload"/>
    </button>
    @endif
</div>
