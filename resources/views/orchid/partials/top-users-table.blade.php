@php
    use Carbon\Carbon;
    $monthName = $monthName ?? Carbon::now()->format('F Y');
    $sortBy = $sortBy ?? 'requests';
    $routeName = $route ?? 'platform.dashboard.requests';
    $queryParams = request()->except('sort_by');
@endphp

<fieldset class="mb-3" id="top-users">
    <legend class="text-body-emphasis px-4 mb-0">Top 10 Users | {{ $monthName }}</legend>
    
    <div class="row mb-2 g-3 g-mb-4">
        <div class="col-12">
            <div class="bg-white rounded p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <small class="text-muted d-block">Top 10 Users</small>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button"
                                onclick="changeSortByUsers('requests')" 
                                class="btn {{ $sortBy === 'requests' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            By Requests
                        </button>
                        <button type="button"
                                onclick="changeSortByUsers('tokens')" 
                                class="btn {{ $sortBy === 'tokens' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            By Tokens
                        </button>
                    </div>
                </div>
                
    @if($topUsers->isEmpty())
        <div class="alert alert-info mb-0 mt-2">
            No user data available for this period.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Role</th>
                        <th class="text-end">Requests</th>
                        <th class="text-end">Tokens</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topUsers as $index => $user)
                        <tr style="cursor: pointer;" 
                            onclick="showUserProviderDetails({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $monthName }}')">
                            <td class="text-muted">{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $user->name }}</strong><br>
                                <small class="text-muted">{{ $user->email }}</small>
                            </td>
                            <td>
                                <small class="text-muted">{{ $user->roles ?? '—' }}</small>
                            </td>
                            <td class="text-end">
                                <span class="badge {{ $sortBy === 'requests' ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ number_format($user->total_requests) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="badge {{ $sortBy === 'tokens' ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ number_format($user->total_tokens) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
            </div>
        </div>
    </div>
</fieldset>

<script>
function changeSortByUsers(sortBy) {
    // Speichere Element-Position statt Scroll-Position
    const topUsersElement = document.getElementById('top-users');
    if (topUsersElement) {
        const rect = topUsersElement.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        sessionStorage.setItem('topUsersPosition', scrollTop + rect.top);
    }
    
    const url = new URL(window.location.href);
    url.searchParams.set('sort_by', sortBy);
    url.hash = 'top-users';
    window.location.href = url.toString();
}

// Stelle Position nach Reload wieder her
document.addEventListener('DOMContentLoaded', function() {
    const topUsersPosition = sessionStorage.getItem('topUsersPosition');
    const hash = window.location.hash;
    
    if (hash === '#top-users' && topUsersPosition !== null) {
        setTimeout(() => {
            window.scrollTo(0, parseInt(topUsersPosition));
            sessionStorage.removeItem('topUsersPosition');
        }, 100);
    }
});
</script>

@include('orchid.partials.user-usage-modal')
