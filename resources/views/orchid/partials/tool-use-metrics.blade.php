@php
    $toolUseMetrics = $toolUseMetrics ?? [];
    $selectedProviders = $selectedProviders ?? [];
@endphp

<fieldset class="mb-3">
    <legend class="text-body-emphasis px-4 mb-0">Tool Use per Provider | {{ $monthName }}</legend>
    
    <div class="row mb-2 g-3 g-mb-4">
    
    @if(count($toolUseMetrics) > 0)
        @foreach($toolUseMetrics as $providerName => $data)
            @php
                // Verwende den identifier (unique_name) für den Filter-Abgleich
                $providerIdentifier = $data['identifier'] ?? $providerName;
                
                // Überprüfe ob dieser Provider ausgewählt ist (filter aus provider-filter.blade.php)
                // Wenn "Total" ausgewählt ist oder keine Provider ausgewählt sind, zeige alle Provider
                $showProvider = empty($selectedProviders) 
                    || in_array('Total', $selectedProviders) 
                    || in_array($providerIdentifier, $selectedProviders);
            @endphp
            
            @if($showProvider)
            <div class="col-sm-6 col-md-4 col-lg-3 mb-3 tool-use-metric-card" data-provider="{{ $providerName }}">
                <div class="card bg-white rounded shadow-sm h-100">
                    <div class="card-body p-3">
                        {{-- Titel: Provider Name --}}
                        <div class="mb-3">
                            <h5 class="text-dark fw-light mb-0">{{ $providerName }}</h5>
                        </div>
                        
                        {{-- Zeile 1: Tool Uses Total --}}
                        <div class="mb-2 pb-2 border-bottom">
                            <h3 class="mb-0 fw-normal text-dark">{{ number_format($data['totalToolUses']) }}</h3>
                            <small class="text-muted">Total Tool Uses</small>
                        </div>
                        
                        {{-- Zeile 2-4: Top 3 Tools mit Anzahl --}}
                        <div class="tool-breakdown">
                            @php
                                $toolCount = 0;
                            @endphp
                            @foreach($data['topTools'] as $toolName => $count)
                                @if($toolCount < 3)
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted">{{ $toolName }}</small>
                                        <span class="text-dark fw-medium">{{ number_format($count) }}</span>
                                    </div>
                                    @php
                                        $toolCount++;
                                    @endphp
                                @endif
                            @endforeach
                            
                            {{-- Fülle mit Platzhaltern auf, wenn weniger als 3 Tools --}}
                            @for($i = $toolCount; $i < 3; $i++)
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted">—</small>
                                    <span class="text-muted">—</span>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
            @endif
        @endforeach
    @else
        <div class="col-12">
            <div class="alert alert-info mb-0 mt-2">
                No tool use data available for this period.
            </div>
        </div>
    @endif
    </div>
</fieldset>
