@if($notification->data['title'] === '🔒 Maintenance Mode Active')
    {{-- Custom view for Maintenance Mode notifications --}}
    <div class="p-4 d-flex align-items-center border-bottom">
        <small class="align-self-start me-2 text-{{ $notification->data['type'] }} @if($notification->read()) opacity @endif">
            <x-orchid-icon path="bs.circle-fill"/>
        </small>

        <div class="ps-3 flex-grow-1">
            <div class="d-flex align-items-center gap-2">
                <div>
                    <span class="fw-bold">{{$notification->data['title'] ?? ''}}</span>
                    <small class="text-muted ps-1 d-inline d-md-none">/ {{ $notification->created_at->diffForHumans() }}</small>
                    <br>
                    <small class="text-muted">
                        The system is in maintenance mode. Share the bypass link with authorized users.
                    </small>
                </div>
                
                <div class="ms-auto d-flex gap-1">
                    <button class="btn btn-sm btn-outline-secondary" 
                            type="button"
                            onclick="copyBypassUrl('{{ $notification->id }}', '{{ $notification->data['bypass_url'] ?? $notification->data['action'] }}')">
                        <x-orchid-icon path="bs.clipboard"/>
                        Copy
                    </button>
                    <a href="{{ $notification->data['bypass_url'] ?? $notification->data['action'] }}" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-primary">
                        <x-orchid-icon path="bs.box-arrow-up-right"/>
                        Open
                    </a>
                </div>
            </div>
        </div>

        <small class="text-muted ms-3 d-none d-md-block text-end" style="min-width: 120px;">
             {{ $notification->created_at->diffForHumans() }}
        </small>
    </div>

    @once
    @push('scripts')
    <script>
        function copyBypassUrl(notificationId, url) {
            navigator.clipboard.writeText(url).then(() => {
                // Show toast notification
                if (window.platform && window.platform.toast) {
                    window.platform.toast('Bypass URL copied to clipboard!', 'success');
                }
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }
    </script>
    @endpush
    @endonce
@else
    {{-- Default notification view --}}
    <button formaction="{{ url()->current() }}/{{ $notification->id }}/maskNotification"
            type="submit"
            class="btn btn-link text-start p-4 d-flex align-items-baseline">

        <small class="align-self-start me-2 text-{{ $notification->data['type'] }} @if($notification->read()) opacity @endif">
            <x-orchid-icon path="bs.circle-fill"/>
        </small>

        <span class="ps-3 text-wrap text-break">
            <span class="w-100">{{$notification->data['title'] ?? ''}}</span>
            <small class="text-muted ps-1 d-inline d-md-none">/ {{ $notification->created_at->diffForHumans() }}</small>
            <br>
            <small class="text-muted w-100">
                {!! $notification->data['message'] ?? '' !!}
            </small>
        </span>

        <small class="text-muted col-3 ms-auto d-none d-md-block text-end">
             {{ $notification->created_at->diffForHumans() }}
        </small>
    </button>
@endif
