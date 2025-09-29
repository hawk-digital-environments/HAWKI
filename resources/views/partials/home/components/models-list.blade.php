<div class="model-selection-panel">
    @if(isset($models['models']) && count($models['models']) > 0)
        @if(config('hawki.ai_config_system'))
            {{-- Database mode: Group and sort by provider display_order --}}
            @php
                // Group models by provider and sort providers by display_order (fallback to alphabetical)
                $groupedModels = collect($models['models'])
                    ->filter(fn($model) => !isset($model['visible']) || $model['visible'])
                    ->groupBy(fn($model) => $model['provider']['name'] ?? $model['provider_name'] ?? 'Unknown')
                    ->sortBy(function ($providerModels, $providerName) {
                        // Get the display_order from the first model of this provider
                        $firstModel = $providerModels->first();
                        $displayOrder = $firstModel['provider_display_order'] ?? $firstModel['provider']['display_order'] ?? null;
                        
                        // Create composite sort key: display_order (nulls last) + provider_name for secondary sort
                        if ($displayOrder !== null) {
                            // Pad with zeros for consistent sorting + provider name for tie-breaking
                            return sprintf('%04d_%s', $displayOrder, $providerName);
                        }
                        
                        // For null values, sort alphabetically at the end (9999 ensures they come last)
                        return sprintf('9999_%s', $providerName);
                    });
            @endphp

            @foreach($groupedModels as $providerName => $providerModels)
                <div class="provider-group">
                    <div class="provider-header">
                        <span class="provider-name">{{ $providerName }}</span>
                    </div>
                    
                    @php
                        // Sort models within each provider by display_order, then by label
                        $sortedModels = $providerModels->sortBy(function ($model) {
                            $displayOrder = $model['display_order'] ?? 9999;
                            return sprintf('%04d_%s', $displayOrder, $model['label']);
                        });
                    @endphp
                    
                    @foreach($sortedModels as $model)
                        <button class="model-selector burger-item"
                                onclick="selectModel(this); closeBurgerMenus()"
                                data-model-id="{{ $model['id'] }}"
                                value="{{ json_encode($model)}}"
                                data-status="{{$model['status']}}"
                                @if($model['status'] === 'offline')
                                    disabled
                                @endif>

                            @switch($model['status'])
                                @case('online')
                                    <span class="dot grn-c"></span>
                                    @break
                                @case('unknown')
                                    <span class="dot org-c"></span>
                                    @break
                                @case('offline')
                                    <span class="dot red-c"></span>
                                    @break
                                @default
                                    <span class="dot red-c"></span>
                            @endswitch
                            <span>{{ $model['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endforeach
        @else
            {{-- Config file mode: Simple list without provider grouping --}}
            @foreach($models['models'] as $model)
                @if(!isset($model['visible']) || $model['visible'])
                    <button class="model-selector burger-item"
                            onclick="selectModel(this); closeBurgerMenus()"
                            data-model-id="{{ $model['id'] }}"
                            value="{{ json_encode($model)}}"
                            data-status="{{$model['status']}}"
                            @if($model['status'] === 'offline')
                                disabled
                            @endif>

                        @switch($model['status'])
                            @case('online')
                                <span class="dot grn-c"></span>
                                @break
                            @case('unknown')
                                <span class="dot org-c"></span>
                                @break
                            @case('offline')
                                <span class="dot red-c"></span>
                                @break
                            @default
                                <span class="dot red-c"></span>
                        @endswitch
                        <span>{{ $model['label'] }}</span>
                    </button>
                @endif
            @endforeach
        @endif
    @else
        <button class="model-selector burger-item" disabled>
            <span class="dot red-c"></span>
            <span>No Models Configured</span>
        </button>
    @endif
</div>
