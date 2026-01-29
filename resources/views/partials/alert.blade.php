<div class="alert {{ $type ?? 'info' }} {{ $class ?? '' }}" {!! $attributes ?? '' !!}>
    <div class="alert-content">
        @if(isset($icon))
            <x-icon name="{{ $icon }}" class="alert-icon"/>
        @endif
        <span class="alert-text">{{ $content ?? $message ?? '' }}</span>
    </div>
</div>
