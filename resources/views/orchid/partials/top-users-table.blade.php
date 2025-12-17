@php
    use Carbon\Carbon;
    $monthName = $monthName ?? Carbon::now()->format('F Y');
    $sortBy = $sortBy ?? 'requests';
    $routeName = $route ?? 'platform.dashboard.requests';
    $queryParams = request()->except('sort_by');
@endphp

<div id="top-users" class="bg-white rounded shadow-sm p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-light mb-0">Top 10 Users - {{ $monthName }}</h3>
        <div class="btn-group" role="group">
            <a href="{{ route($routeName, array_merge($queryParams, ['sort_by' => 'requests'])) }}#top-users" 
               class="btn btn-sm {{ $sortBy === 'requests' ? 'btn-primary' : 'btn-outline-primary' }}">
                Sort by Requests
            </a>
            <a href="{{ route($routeName, array_merge($queryParams, ['sort_by' => 'tokens'])) }}#top-users" 
               class="btn btn-sm {{ $sortBy === 'tokens' ? 'btn-primary' : 'btn-outline-primary' }}">
                Sort by Tokens
            </a>
        </div>
    </div>
    
    @if($topUsers->isEmpty())
        <div class="alert alert-info">
            No user data available for this period.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 60px;">#</th>
                        <th>User</th>
                        <th>Role</th>
                        <th class="text-end">Total Requests</th>
                        <th class="text-end">Total Tokens</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topUsers as $index => $user)
                        <tr style="cursor: pointer;" 
                            onclick="showUserProviderDetails({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $monthName }}')">
                            <td class="text-center">
                                <span class="text-muted">{{ $index + 1 }}</span>
                            </td>
                            <td>
                                <div>
                                    <strong>{{ $user->name }}</strong>
                                </div>
                                <small class="text-muted">{{ $user->email }}</small>
                            </td>
                            <td>
                                <small class="text-muted">{{ $user->roles ?? '—' }}</small>
                            </td>
                            <td class="text-end">
                                {{ number_format($user->total_requests) }}
                            </td>
                            <td class="text-end">
                                {{ number_format($user->total_tokens) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@include('orchid.partials.user-usage-modal')
