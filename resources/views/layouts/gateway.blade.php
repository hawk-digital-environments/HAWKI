<!DOCTYPE html>
<html class="lightMode">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
	<meta name="csrf-token" content="{{ csrf_token() }}">


    <title>{{ env('APP_NAME') }}</title>

    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/handshake_style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/settings_style.css') }}">

    <script src="{{ asset('js_v2.0.1_f1/functions.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/handshake_functions.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/encryption.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/settings_functions.js') }}"></script>
    <script src="{{ asset('js_v2.0.1_f1/announcements.js') }}"></script>
    @vite('resources/js/app.js')

	{!! $settingsPanel !!}

    <script>
		SwitchDarkMode(false);
		UpdateSettingsLanguage('{{ Session::get("language")['id'] }}');
	</script>

</head>
<body>
    @include('partials.overlay')

    @yield('content')
</body>
</html>
