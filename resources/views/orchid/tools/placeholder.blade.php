<div class="bg-white rounded shadow-sm p-4 position-relative">
    <div class="row align-items-center">
        <div class="col-auto">
            @if(isset($icon))
                <x-orchid-icon path="{{ $icon }}" class="h1 text-muted" />
            @else
                <x-orchid-icon path="bs.gear" class="h1 text-muted" />
            @endif
        </div>
        <div class="col">
            <h4 class="text-black font-weight-light">
                {{ $title ?? 'Under Development' }}
            </h4>
            <p class="text-muted mb-0">
                {{ $description ?? 'This section is currently under development.' }}
            </p>
        </div>
    </div>
    
    <div class="mt-4 p-3 bg-light rounded">
        <div class="d-flex align-items-center">
            <x-orchid-icon path="bs.info-circle" class="me-2 text-info" />
            <small class="text-muted">
                <strong>Coming Soon:</strong> 
                Advanced AI tools integration, custom extensions management, and workflow automation capabilities.
            </small>
        </div>
    </div>
</div>