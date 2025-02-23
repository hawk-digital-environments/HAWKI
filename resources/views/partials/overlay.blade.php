
<div id="overlay"

@if($activeOverlay)
    style =" visibility: visible; opacity: 1;"
@else
    style ="visibility: hidden; opacity: 0;"
@endif
>
    <div id="overlay-wrapper">
        <div id="overlay-logo">
            <img src="{{ asset('img/logo.png') }}" alt="">
        </div>  
    </div>
</div>

