<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="lightMode">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name') }}</title>

    <link rel="icon" href="{{ \App\Services\Frontend\Config\BrandingConfig::svgUri(config('hawki.branding.favicon')) ?? asset('favicon.ico') }}">
    <link rel="manifest" href="{{ url('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ url('pwa/icons/apple-touch-icon.png') }}">
    <meta name="theme-color" content="{{ \App\Services\Frontend\View\BrandTheme::accentColor() ?? '#2f2abf' }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

    <x-css-layers/>
    <x-brand-theme/>

    @vite('resources/js/app.ts')
    @vite('resources/css/app.css')
</head>
<body>
<div id="hawki-app" data-loading-label="{{ __('ui.loading') }}"></div>
</body>
</html>
