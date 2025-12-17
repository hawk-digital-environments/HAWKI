@php
    $selectedRoles = $selectedRoles ?? [];
    $availableRoles = $availableRoles ?? [];
    $routeName = $route ?? 'platform.dashboard.users';
    $queryParams = request()->except('roles');
@endphp

@if(!empty($availableRoles))
<div class="d-flex justify-content-end mb-3">
    <div id="role-filter" class="bg-white rounded shadow-sm p-3" style="width: auto;">
        <div class="d-flex justify-content-between align-items-center gap-3">
            
            <div class="btn-group btn-group-sm" role="group">
            @foreach($availableRoles as $role)
                @php
                    $isActive = in_array($role, $selectedRoles);
                    
                    // Toggle Role in Array
                    if ($isActive) {
                        $newRoles = array_diff($selectedRoles, [$role]);
                    } else {
                        $newRoles = array_merge($selectedRoles, [$role]);
                    }
                    
                    $url = route($routeName, array_merge($queryParams, ['roles' => $newRoles])) . '#role-filter';
                @endphp
                <a href="{{ $url }}" 
                   class="btn btn-sm {{ $isActive ? 'btn-primary' : 'btn-outline-primary' }}"
                   title="{{ $isActive ? 'Hide' : 'Show' }} {{ $role }}">
                    {{ $role }}
                </a>
            @endforeach
        </div>
    </div>
        @if(empty($selectedRoles))
            <small class="text-muted d-block mt-2">
                <i class="me-1">ℹ️</i> No roles selected. Select at least one role to display data.
            </small>
        @endif
    </div>
</div>
@endif
