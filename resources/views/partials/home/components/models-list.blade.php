<div class="model-selection-panel">
    @if(isset($models['models']) && count($models['models']) > 0)
        @foreach($models['models'] as $model)
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
    @else
        <button class="model-selector burger-item" disabled>
            <span class="dot red-c"></span>
            <span>No Models Configured</span>
        </button>
    @endif
</div>
