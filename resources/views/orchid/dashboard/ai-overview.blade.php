@php
    // In Orchid Blade-Views sind query() Daten direkt als Variablen verfügbar
    $activeProviders = $system['activeProviders'] ?? 0;
    $top5Providers = $system['top5Providers'] ?? [];
    $activeModels = $system['activeModels'] ?? 0;
    $top5Models = $system['top5Models'] ?? [];
    $excludeSystemModels = $system['excludeSystemModels'] ?? false;
@endphp

<fieldset class="mb-3">
    <legend class="text-body-emphasis px-4 mb-0">AI Infrastructure</legend>
    
    <div class="row mb-2 g-3 g-mb-4">
        {{-- Active Providers Card --}}
        <div class="col-sm-6 col-md-6 col-xl-6">
            <div class="metric-box position-relative p-4 bg-white rounded h-100">
                <small class="text-muted d-block mb-1">Active Providers</small>
                <p class="h3 text-body-emphasis fw-light mt-auto">{{ $activeProviders }}</p>
            </div>
        </div>

        {{-- Active Models Card --}}
        <div class="col-sm-6 col-md-6 col-xl-6">
            <div class="metric-box position-relative p-4 bg-white rounded h-100">
                <small class="text-muted d-block mb-1">AI Models</small>
                <p class="h3 text-body-emphasis fw-light mt-auto">{{ $activeModels }}</p>
            </div>
        </div>
    </div>

    <div class="row mb-2 g-3 g-mb-4">
        {{-- Top 5 Providers Card --}}
        <div class="col-sm-6 col-md-6 col-xl-6">
            <div class="bg-white rounded p-4 h-100 d-flex flex-column">
                <small class="text-muted d-block mb-2">Top 5 Providers</small>

                @if(is_array($top5Providers) && count($top5Providers) > 0)
                    <div class="table-responsive flex-grow-1">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Provider</th>
                                    <th class="text-end">Requests</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($top5Providers as $index => $provider)
                                <tr>
                                    <td class="text-muted">{{ $index + 1 }}</td>
                                    <td>
                                        <strong>{{ $provider['provider'] }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-primary">{{ $provider['requests'] }}</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mb-0 mt-2">
                        No provider usage data available.
                    </div>
                @endif
            </div>
        </div>

        {{-- Top 5 Models Card --}}
        <div class="col-sm-6 col-md-6 col-xl-6">
            <div class="bg-white rounded p-4 h-100 d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <small class="text-muted d-block">Top 5 Most Used Models</small>
                    <div class="form-check form-switch mb-0">
                        <input 
                            class="form-check-input" 
                            type="checkbox" 
                            id="excludeSystemModels" 
                            {{ $excludeSystemModels ? 'checked' : '' }}
                            onchange="toggleSystemModels(this)"
                        >
                        <label class="form-check-label small text-muted" for="excludeSystemModels">
                            Hide System Models
                        </label>
                    </div>
                </div>

                @if(is_array($top5Models) && count($top5Models) > 0)
                    <div class="table-responsive flex-grow-1">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Provider / Model</th>
                                    <th class="text-end">Requests</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($top5Models as $index => $model)
                                <tr>
                                    <td class="text-muted">{{ $index + 1 }}</td>
                                    <td>
                                        <strong>{{ $model['provider'] }} / {{ $model['model'] }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-primary">{{ $model['requests'] }}</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mb-0 mt-2">
                        No model usage data available{{ $excludeSystemModels ? ' (system models excluded)' : '' }}.
                    </div>
                @endif
            </div>
        </div>
    </div>
</fieldset>

<script>
function toggleSystemModels(checkbox) {
    // Speichere aktuelle Scroll-Position
    sessionStorage.setItem('scrollPosition', window.scrollY);
    
    const url = new URL(window.location.href);
    if (checkbox.checked) {
        url.searchParams.set('exclude_system_models', '1');
    } else {
        url.searchParams.delete('exclude_system_models');
    }
    window.location.href = url.toString();
}

// Stelle Scroll-Position nach Reload wieder her
document.addEventListener('DOMContentLoaded', function() {
    const scrollPosition = sessionStorage.getItem('scrollPosition');
    if (scrollPosition !== null) {
        window.scrollTo(0, parseInt(scrollPosition));
        sessionStorage.removeItem('scrollPosition');
    }
});
</script>
