@php
    $selectedRoles = $selectedRoles ?? [];
    $availableRoles = $availableRoles ?? [];
    $routeName = $route ?? 'platform.dashboard.users';
    $queryParams = request()->except('roles');
@endphp

@if(!empty($availableRoles))
<fieldset class="mb-3">
    <legend class="text-body-emphasis px-4 mb-0">Role Filter</legend>
    
    <div class="row mb-2 g-3 g-mb-4">
        <div class="col-12">
            <div class="bg-white rounded p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <small class="text-muted d-block">Select Roles to Display</small>
                    <div class="btn-group btn-group-sm flex-wrap" role="group">
                        @foreach($availableRoles as $role)
                            @php
                                $isActive = in_array($role, $selectedRoles);
                            @endphp
                            <button type="button"
                                    onclick="toggleRole('{{ $role }}', {{ $isActive ? 'true' : 'false' }})" 
                                    class="btn {{ $isActive ? 'btn-primary' : 'btn-outline-secondary' }}"
                                    title="{{ $isActive ? 'Hide' : 'Show' }} {{ $role }}">
                                {{ $role }}
                            </button>
                        @endforeach
                    </div>
                </div>
                
                @if(empty($selectedRoles))
                    <div class="alert alert-info mb-0 mt-2">
                        <i class="me-1">ℹ️</i> No roles selected. Select at least one role to display data.
                    </div>
                @endif
            </div>
        </div>
    </div>
</fieldset>

<script>
const availableRoles = @json($availableRoles);

function toggleRole(role, isActive) {
    // Speichere aktuelle Scroll-Position
    sessionStorage.setItem('scrollPosition', window.scrollY);
    
    const url = new URL(window.location.href);
    let currentRoles = url.searchParams.getAll('roles[]');
    
    // If NO roles are in parameters, it means PHP is showing defaults (ALL roles).
    // We must mirror this state in JS before toggling.
    if (currentRoles.length === 0 && !url.searchParams.has('roles')) {
        currentRoles = [...availableRoles];
    }
    
    let newRoles;
    if (isActive) {
        // Remove role
        newRoles = currentRoles.filter(r => r !== role);
    } else {
        // Add role
        newRoles = [...currentRoles, role];
    }
    
    // Clear old parameters
    url.searchParams.delete('roles[]');
    url.searchParams.delete('roles');
    
    // If newRoles contains all available roles, keep URL clean by not adding them
    if (newRoles.length < availableRoles.length) {
        newRoles.forEach(r => url.searchParams.append('roles[]', r));
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
@endif
