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

    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/apps_connect_style.css') }}">
    @yield('styles')

    @vite('resources/js/app.js')
    <script src="{{ asset('js_v2.0.1_f1/settings_functions.js') }}"></script>
    @yield('scripts')
    <script>
        SwitchDarkMode(false);
        UpdateSettingsLanguage('{{ Session::get("language")['id'] }}');
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
