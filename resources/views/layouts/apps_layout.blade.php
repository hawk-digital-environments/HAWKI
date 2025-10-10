<!DOCTYPE html>
<html class="lightMode">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}@hasSection('title')
            - @yield('title')
        @endif</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <link rel="stylesheet" href="{{ asset('css_v2.1.0/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.1.0/apps_connect_style.css') }}">
    @yield('styles')

    @vite('resources/js/app.js')
    <script src="{{ asset('js_v2.1.0/settings_functions.js') }}"></script>
    @yield('scripts')
    <script>
        SwitchDarkMode(false);
        UpdateSettingsLanguage('<x-current-locale/>');
    </script>
</head>
<body>
<div class="wrapper">
    @component('partials.center-content')
        @slot('content')
            @yield('content')
            @hasSection('buttons')
                <div class="nav-buttons">
                    @yield('buttons')
                </div>
            @endif
        @endslot
    @endcomponent
</div>
</body>
</html>
