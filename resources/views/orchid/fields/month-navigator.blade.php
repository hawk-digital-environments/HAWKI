@php
    use Carbon\Carbon;
    
    $currentMonth = $attributes['currentMonth'] ?? request('monthly_date', Carbon::now()->format('Y-m'));
    $monthDate = Carbon::createFromFormat('Y-m', $currentMonth);
    
    $prevMonth = $monthDate->copy()->subMonth()->format('Y-m');
    $nextMonth = $monthDate->copy()->addMonth()->format('Y-m');
    
    $monthName = $monthDate->format('F Y');
    
    $routeName = $attributes['routeName'] ?? 'platform.dashboard.users';
    
    // Preserve other query parameters
    $queryParams = request()->except('monthly_date');
@endphp

<div class="mb-3">
    @isset($title)
        <label class="form-label">
            {{ $title }}
        </label>
    @endisset

    <div class="d-flex align-items-center justify-content-between bg-light rounded p-3 border">
        <!-- Previous Month Arrow -->
        <a href="{{ route($routeName, array_merge($queryParams, ['monthly_date' => $prevMonth])) }}" 
           class="btn btn-link text-decoration-none text-primary p-2"
           title="Previous Month">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
            </svg>
        </a>

        <!-- Current Month Display -->
        <div class="text-center flex-grow-1">
            <strong class="fs-5 text-dark">{{ $monthName }}</strong>
        </div>

        <!-- Next Month Arrow -->
        <a href="{{ route($routeName, array_merge($queryParams, ['monthly_date' => $nextMonth])) }}" 
           class="btn btn-link text-decoration-none text-primary p-2"
           title="Next Month">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-chevron-right" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
            </svg>
        </a>
    </div>

    @isset($help)
        <small class="form-text text-muted d-block mt-2">{!! $help !!}</small>
    @endisset
</div>
