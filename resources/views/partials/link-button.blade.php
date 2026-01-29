<a
    href="{{$href ?? '#'}}"
    @if(isset($onclick)) onclick="{{$onclick}}" @endif
    @if($blank ?? false) target="_blank" rel="noopener noreferrer" @endif
    class="link-button {{$class ?? null}}"
>
    {{$slot}}
    {{$content ?? null}}
    <svg viewBox="0 0 25 25" class="link-button-arrow">
        <g class="button-path-stroke-color" fill="none" stroke-width="2" stroke-linecap="round"
           stroke-linejoin="round">
            <path d="M 12 16 l 4 -4 l -4 -4 M 8 12 H 16"/>
        </g>
    </svg>
</a>
