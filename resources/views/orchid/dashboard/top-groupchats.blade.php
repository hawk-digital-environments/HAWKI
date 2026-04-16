@php
    // In Orchid Blade-Views sind query() Daten direkt als Variablen verfügbar
    $rooms = $groupchat['topRooms'] ?? [];
    $sortBy = $groupchat['sortBy'] ?? 'messages';
@endphp

<fieldset class="mb-3">
    <legend class="text-body-emphasis px-4 mb-0">Most Active Groupchats</legend>
    
    <div class="row mb-2 g-3 g-mb-4">
        <div class="col-12">
            <div class="bg-white rounded p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <small class="text-muted d-block">Top 5 Rooms</small>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button"
                                onclick="changeSortBy('messages')" 
                                class="btn {{ $sortBy === 'messages' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            By Messages
                        </button>
                        <button type="button"
                                onclick="changeSortBy('users')" 
                                class="btn {{ $sortBy === 'users' ? 'btn-primary' : 'btn-outline-secondary' }}">
                            By Users
                        </button>
                    </div>
                </div>

                @if(is_array($rooms) && count($rooms) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Room Name</th>
                                    <th class="text-end">Messages</th>
                                    <th class="text-end">Users</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rooms as $index => $room)
                                <tr>
                                    <td class="text-muted">{{ $index + 1 }}</td>
                                    <td>
                                        <strong>{{ $room['name'] }}</strong>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge {{ $sortBy === 'messages' ? 'bg-success' : 'bg-secondary' }}">{{ $room['messages'] }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge {{ $sortBy === 'users' ? 'bg-success' : 'bg-secondary' }}">{{ $room['users'] }}</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mb-0 mt-2">
                        No groupchat activity data available yet.
                    </div>
                @endif
            </div>
        </div>
    </div>
</fieldset>

<script>
function changeSortBy(sortBy) {
    // Speichere aktuelle Scroll-Position
    sessionStorage.setItem('scrollPosition', window.scrollY);
    
    const url = new URL(window.location.href);
    url.searchParams.set('groupchat_sort_by', sortBy);
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
