<div class="model-selection-panel">
    @foreach($models['models'] as $model)
        <button class="model-selector burger-item" onclick="selectModel(this); closeBurgerMenus()" value="{{ json_encode($model) }}">
            {{ $model['label'] }}
        </button>
    @endforeach
</div>