@php
    use Carbon\Carbon;
    
    $currentMonth = $currentMonth ?? request('monthly_date', Carbon::now()->format('Y-m'));
    $monthDate = Carbon::createFromFormat('Y-m', $currentMonth);
    
    $prevMonth = $monthDate->copy()->subMonth()->format('Y-m');
    $nextMonth = $monthDate->copy()->addMonth()->format('Y-m');
    $todayMonth = Carbon::now()->format('Y-m');
    
    $monthName = $monthDate->format('F Y');
    
    $routeName = $route ?? 'platform.dashboard.users';
    
    // Preserve other query parameters
    $queryParams = request()->except('monthly_date');
@endphp

<div class="mb-3">
    <div class="d-flex align-items-center justify-content-between bg-white rounded p-2 border">
        <!-- Previous Month Arrow -->
        <a href="{{ route($routeName, array_merge($queryParams, ['monthly_date' => $prevMonth])) }}" 
           class="btn btn-sm btn-link text-decoration-none text-primary p-1"
           title="Previous Month">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
            </svg>
        </a>

        <!-- Current Month Display (clickable to jump to current month) -->
        <div class="text-center flex-grow-1 px-2">
            <a href="{{ route($routeName, array_merge($queryParams, ['monthly_date' => $todayMonth])) }}" 
               class="text-decoration-none text-dark"
               title="Jump to current month"
               style="cursor: pointer;">
                <strong style="font-size: 0.95rem;">{{ $monthName }}</strong>
            </a>
        </div>

        <!-- Next Month Arrow -->
        <a href="{{ route($routeName, array_merge($queryParams, ['monthly_date' => $nextMonth])) }}" 
           class="btn btn-sm btn-link text-decoration-none text-primary p-1"
           title="Next Month">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
            </svg>
        </a>
    </div>
</div>
