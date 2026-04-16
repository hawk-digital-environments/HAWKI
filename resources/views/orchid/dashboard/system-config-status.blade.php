@php
    // In Orchid Blade-Views sind query() Daten direkt als Variablen verfügbar
    $authStatus = $config['authStatus'] ?? 'info';
    $authMessage = $config['authMessage'] ?? 'Unknown';
    $backupStatus = $config['backupStatus'] ?? 'info';
    $backupMessage = $config['backupMessage'] ?? 'Unknown';
    $providersStatus = $config['providersStatus'] ?? 'info';
    $providersMessage = $config['providersMessage'] ?? 'Unknown';
    $providersIssues = $config['providersIssues'] ?? [];

    // Icons für jeden Status
    $statusIcons = [
        'success' => 'bs.check-circle',
        'warning' => 'bs.exclamation-triangle',
        'danger' => 'bs.x-circle',
        'info' => 'bs.info-circle',
    ];

    // Farben für Status
    $statusColors = [
        'success' => 'text-success',
        'warning' => 'text-warning',
        'danger' => 'text-danger',
        'info' => 'text-info',
    ];

    $hasIssues = !empty($providersIssues);
@endphp

<div class="mb-3">
    <div class="row">
        {{-- Authentication Status --}}
        <div class="col-sm-6 col-md-4 col-xl-4">
            <div class="metric-box position-relative d-flex flex-column p-4 bg-white rounded h-100">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <small class="text-muted text-uppercase">Authentication</small>
                    </div>
                    <x-orchid-icon :path="$statusIcons[$authStatus] ?? 'bs.info-circle'" 
                                   class="{{ $statusColors[$authStatus] ?? 'text-muted' }} ms-2" 
                                   width="1.5em" 
                                   height="1.5em"/>
                </div>
                <div class="mt-2">
                    <h3 class="mb-0">
                        <span class="{{ $statusColors[$authStatus] ?? 'text-muted' }}">
                            {{ ucfirst($authStatus) }}
                        </span>
                    </h3>
                    <p class="text-muted small mb-0 mt-1">{{ $authMessage }}</p>
                </div>
            </div>
        </div>

        {{-- Backup Status --}}
        <div class="col-sm-6 col-md-4 col-xl-4">
            <div class="metric-box position-relative d-flex flex-column p-4 bg-white rounded h-100">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <small class="text-muted text-uppercase">Backup Status</small>
                    </div>
                    <x-orchid-icon :path="$statusIcons[$backupStatus] ?? 'bs.info-circle'" 
                                   class="{{ $statusColors[$backupStatus] ?? 'text-muted' }} ms-2" 
                                   width="1.5em" 
                                   height="1.5em"/>
                </div>
                <div class="mt-2">
                    <h3 class="mb-0">
                        <span class="{{ $statusColors[$backupStatus] ?? 'text-muted' }}">
                            {{ ucfirst($backupStatus) }}
                        </span>
                    </h3>
                    <p class="text-muted small mb-0 mt-1">{{ $backupMessage }}</p>
                </div>
            </div>
        </div>

        {{-- AI Providers Status --}}
        <div class="col-sm-6 col-md-4 col-xl-4">
            <div class="metric-box position-relative d-flex flex-column p-4 bg-white rounded h-100 {{ $hasIssues ? 'cursor-pointer' : '' }}"
                 @if($hasIssues) 
                 data-bs-toggle="modal" 
                 data-bs-target="#providerIssuesModal"
                 style="cursor: pointer;"
                 @endif>
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <small class="text-muted text-uppercase">AI Providers</small>
                    </div>
                    <x-orchid-icon :path="$statusIcons[$providersStatus] ?? 'bs.info-circle'" 
                                   class="{{ $statusColors[$providersStatus] ?? 'text-muted' }} ms-2" 
                                   width="1.5em" 
                                   height="1.5em"/>
                </div>
                <div class="mt-2">
                    <h3 class="mb-0">
                        <span class="{{ $statusColors[$providersStatus] ?? 'text-muted' }}">
                            {{ ucfirst($providersStatus) }}
                        </span>
                    </h3>
                    <p class="text-muted small mb-0 mt-1">
                        {{ $providersMessage }}
                        @if($hasIssues)
                            <br><small class="text-muted">Click for details</small>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal für Provider Issues --}}
@if($hasIssues)
<div class="modal fade" id="providerIssuesModal" tabindex="-1" aria-labelledby="providerIssuesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark p-4 rounded-top">
                <h5 class="modal-title mb-0" id="providerIssuesModalLabel">
                    AI Provider Configuration Issues
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted m-4">
                    The following issues may need to be resolved:
                </p>
                <div class="m-4">
                    @foreach($providersIssues as $issue)
                        @php
                            [$assistant, $problems] = explode(': ', $issue, 2);
                            $problemsList = explode(', ', $problems);
                        @endphp
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex w-100 justify-content-between align-items-start">
                                <h6 class="mb-1">
                                    <code>{{ $assistant }}</code>
                                </h6>
                                <span class="badge bg-danger">{{ count($problemsList) }} issue(s)</span>
                            </div>
                            <ul class="mb-0 mt-2">
                                @foreach($problemsList as $problem)
                                    <li class="text-danger">{{ trim($problem) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('platform.models.assistants') }}" class="btn btn-link">
                    Go to AI Assistants Settings
                </a>
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endif
