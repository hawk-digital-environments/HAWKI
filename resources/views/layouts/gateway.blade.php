<!DOCTYPE html>
<html class="lightMode">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
	<meta name="csrf-token" content="{{ csrf_token() }}">


    <title>{{ env('APP_NAME') }}</title>

    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/handshake_style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/settings_style.css') }}">

    <script src="{{ asset('js/functions.js') }}"></script>
    <script src="{{ asset('js/handshake_functions.js') }}"></script>
    <script src="{{ asset('js/encryption.js') }}"></script>
    <script src="{{ asset('js/settings_functions.js') }}"></script>
    <script src="{{ asset('js/announcements.js') }}"></script>
    <script src="{{ asset('js/passkeyInputs.js') }}"></script>
    @vite('resources/js/app.js')

	{!! $settingsPanel !!}

    <script>
		SwitchDarkMode(false);
        UpdateSettingsLanguage('<x-current-locale/>');
	</script>

</head>
<body>
    @include('partials.overlay')

    @yield('content')
</body>
</html>
