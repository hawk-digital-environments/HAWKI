@php
    $selectedProviders = $selectedProviders ?? [];
    $availableProviders = $availableProviders ?? [];
    $routeName = $route ?? 'platform.dashboard.requests';
    $queryParams = request()->except('providers');
@endphp

@if(!empty($availableProviders))
<div class="d-flex justify-content-end mb-3">
    <div id="provider-filter" class="bg-white rounded shadow-sm p-3" style="width: auto;">
        <div class="d-flex justify-content-between align-items-center gap-3">
            
            <div class="btn-group btn-group-sm" role="group">
            @foreach($availableProviders as $provider)
                @php
                    $isActive = in_array($provider, $selectedProviders);
                    
                    // Toggle Provider in Array
                    if ($isActive) {
                        $newProviders = array_diff($selectedProviders, [$provider]);
                    } else {
                        $newProviders = array_merge($selectedProviders, [$provider]);
                    }
                    
                    $url = route($routeName, array_merge($queryParams, ['providers' => $newProviders])) . '#provider-filter';
                @endphp
                <a href="{{ $url }}" 
                   class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-primary' }}"
                   title="{{ $isActive ? 'Hide' : 'Show' }} {{ $provider }}">
                    {{ $provider }}
                </a>
            @endforeach
        </div>
    </div>
        @if(empty($selectedProviders))
            <small class="text-muted d-block mt-2">
                <i class="me-1">ℹ️</i> No providers selected. Select at least one provider to display data.
            </small>
        @endif
    </div>
</div>
@endif
