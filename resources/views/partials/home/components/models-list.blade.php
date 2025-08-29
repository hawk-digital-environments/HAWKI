<div class="model-selection-panel">
    @foreach($models['models'] as $model)
        <button class="model-selector burger-item" onclick="selectModel(this); closeBurgerMenus()" data-model-id="{{ $model['id'] }}" value="{{ json_encode($model) }}">


            @if(array_key_exists('status',$model))
                @switch($model['status'])
                    @case('ready')
                        <span class="dot grn-c"></span>
                        @break
                    @case('loading')
                        <span class="dot org-c"></span>
                        @break
                    @case('unavailable')
                        <span class="dot red-c"></span>
                        @break
                    @default
                        <span class="dot org-c"></span>
                @endswitch
            @else
            <span class="dot grn-c"></span>
            @endif
            <span>{{ $model['label'] }}<span>

        </button>
    @endforeach
</div>
