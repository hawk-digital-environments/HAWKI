@php
    use Carbon\Carbon;
    
    $currentDate = $currentDate ?? request('date', Carbon::now()->format('Y-m-d'));
    $dateObj = Carbon::createFromFormat('Y-m-d', $currentDate);
    
    $prevDate = $dateObj->copy()->subDay()->format('Y-m-d');
    $nextDate = $dateObj->copy()->addDay()->format('Y-m-d');
    $today = Carbon::now()->format('Y-m-d');
    
    $dateDisplay = $dateObj->format('l, F j, Y');
    
    $routeName = $route ?? 'platform.dashboard.requests';
    
    // Preserve other query parameters
    $queryParams = request()->except('date');
    $queryString = http_build_query($queryParams);
    $baseUrl = route($routeName);
@endphp

<div id="date-selector" class="mb-3 d-flex justify-content-end">
<div style="max-width: 400px; width: 100%;">
    <div class="d-flex align-items-center justify-content-between bg-white rounded p-2 border">
        <!-- Previous Day Arrow -->
        <a href="{{ route($routeName, array_merge($queryParams, ['date' => $prevDate])) }}#date-selector" 
           class="btn btn-sm btn-link text-decoration-none text-primary p-1"
           title="Previous Day">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
            </svg>
        </a>

        <!-- Current Date Display (clickable to open date picker) -->
        <div class="text-center flex-grow-1 px-2 position-relative">
            <div onclick="document.getElementById('date-picker-input').showPicker()" 
                 class="text-decoration-none text-dark"
                 title="Click to select date"
                 style="cursor: pointer;">
                <strong style="font-size: 0.95rem;">{{ $dateDisplay }}</strong>
            </div>
            <input type="date" 
                   id="date-picker-input" 
                   value="{{ $currentDate }}"
                   style="position: absolute; opacity: 0; pointer-events: none;"
                   onchange="window.location.href='{{ $baseUrl }}?{{ $queryString }}{{ $queryString ? '&' : '' }}date=' + this.value + '#date-selector'">
        </div>

        <!-- Next Day Arrow -->
        <a href="{{ route($routeName, array_merge($queryParams, ['date' => $nextDate])) }}#date-selector" 
           class="btn btn-sm btn-link text-decoration-none text-primary p-1"
           title="Next Day">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
            </svg>
        </a>
    </div>
</div>
</div>
