<div class="mb-3">
    @isset($title)
        <legend class="text-body-emphasis px-4 mb-0">
            {{ __($title) }}
        </legend>
    @endisset
    <div class="row mb-2 g-3 g-mb-4">
        @foreach($metrics as $key => $metric)
            <div class="col">
                <div class="p-4 bg-white rounded shadow-sm h-100 d-flex flex-column">
                    <small class="text-muted d-block mb-1">{{ __($key) }}</small>
                    <p class="h1 text-body-emphasis fw-light mt-auto d-flex align-items-center justify-content-between">
                        <span>
                            {{ is_array($metric) ? $metric['value'] : $metric }}
                            @if(isset($metric['diff']) && (float)$metric['diff'] !== 0.0)
                                <small class="small {{ (float)$metric['diff'] < 0 ? 'text-danger': 'text-success' }}">
                                    {{ round($metric['diff'], 2) }} %
                                </small>
                            @endif
                        </span>
                        @if(is_array($metric) && isset($metric['icon']))
                            <x-orchid-icon class="icon-big" path="{{ $metric['icon'] }}" style="width: auto; height: 2.5rem;"/>
                        @endif
                    </p>
                </div>
            </div>
        @endforeach
    </div>
</div>
