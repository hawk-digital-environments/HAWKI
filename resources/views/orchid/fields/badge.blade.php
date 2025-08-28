<div class="mb-3">
    @isset($title)
        <label class="form-label">
            {{ $title }}
            @if(isset($required) && $required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endisset

    <div>
        <span class="badge {{ $badgeClass ?? 'bg-secondary-subtle text-secondary-emphasis' }} rounded-pill fs-6">
            {{ $value ?? $text ?? '' }}
        </span>
    </div>

    @isset($help)
        <small class="form-text text-muted">{!! $help !!}</small>
    @endisset
</div>
