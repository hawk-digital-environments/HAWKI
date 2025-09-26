
<div id="overlay"

@if($activeOverlay)
    style =" visibility: visible; opacity: 1;"
@else
    style ="visibility: hidden; opacity: 0;"
@endif
>
    <div id="overlay-wrapper">
        <div id="overlay-logo">
           {{-- Load logo from database --}}
           @php
               $dbLogo = \App\Models\AppSystemImage::getByName('logo_svg');
           @endphp
           
           @if($dbLogo && file_exists(public_path($dbLogo->file_path)))
                {{-- Use the uploaded logo from database --}}
                <img src="{{ asset($dbLogo->file_path) }}" alt="HAWKI Logo" style="height: 10vw;">
            @else
                {{-- Fallback to default HAWKI logo --}}
                <img src="{{ asset('img/logo.png') }}" alt="HAWKI Logo">            
            @endif
        </div>  
    </div>
</div>

