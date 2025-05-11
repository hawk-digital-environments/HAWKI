
<div id="overlay"

@if($activeOverlay)
    style =" visibility: visible; opacity: 1;"
@else
    style ="visibility: hidden; opacity: 0;"
@endif
>
    <div id="overlay-wrapper">
        <div id="overlay-logo">
           {{--  <img src="{{ asset('img/logo.png') }}" alt="">  --}}
           <img src="{{ route('system.image', 'logo_svg') }}" alt="Logo" style="height: 15vh;">
    </div>
</div>

