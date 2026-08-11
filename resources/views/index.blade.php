<!DOCTYPE html>
<html class="lightMode">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <x-css-layers/>

    @vite('resources/js/app.ts')
    @vite('resources/css/app.css')
</head>
<body>
<div id="hawki-app"></div>
</body>
</html>
